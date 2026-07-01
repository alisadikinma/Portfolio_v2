<?php

namespace Tests\Unit;

use App\Exceptions\GeminiGenClientException;
use App\Services\GeminiGenClientBridge;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class GeminiGenClientBridgeTest extends TestCase
{
    private function cmdString($process): string
    {
        return is_array($process->command)
            ? implode(' ', $process->command)
            : (string) $process->command;
    }

    public function test_submit_returns_uuid_from_stdout(): void
    {
        Process::fake([
            '*' => Process::result(output: 'submitted: uuid=abc-123-def status=1'),
        ]);

        $uuid = app(GeminiGenClientBridge::class)->submit(
            'generate_image',
            ['prompt' => 'a cat', 'aspect_ratio' => '16:9', 'style' => 'Photorealistic'],
            ['https://x/ref.png'],
            'nano-banana-pro'
        );

        $this->assertSame('abc-123-def', $uuid);
    }

    public function test_submit_builds_local_image_argv(): void
    {
        Process::fake(['*' => Process::result(output: 'submitted: uuid=u1 status=1')]);

        app(GeminiGenClientBridge::class)->submit(
            'generate_image',
            ['prompt' => 'a cat', 'aspect_ratio' => '3:4', 'style' => 'Photorealistic'],
            ['https://x/face.png'],
            'nano-banana-pro'
        );

        Process::assertRan(function ($process) {
            $c = $this->cmdString($process);

            return str_contains($c, '-m scripts.geminigen_image')
                && str_contains($c, ' image ')
                && str_contains($c, 'a cat')
                && str_contains($c, '--model nano-banana-pro')
                && str_contains($c, '--aspect 3:4')
                && str_contains($c, '--style Photorealistic')
                && str_contains($c, '--ref https://x/face.png')
                && str_contains($c, '--no-wait')
                // never leak the api key onto argv
                && ! str_contains(strtolower($c), '--api-key');
        });
    }

    public function test_non_enum_aspect_4x5_is_remapped_to_3x4(): void
    {
        // Blog inline segments author 4:5; the old HTTP path accepted it but the
        // strict CLI enum argparse-exit-2's on it. Bridge must remap → 3:4.
        Process::fake(['*' => Process::result(output: 'submitted: uuid=u1 status=1')]);

        app(GeminiGenClientBridge::class)->submit(
            'generate_image',
            ['prompt' => 'a cat', 'aspect_ratio' => '4:5', 'style' => 'Photorealistic'],
            [],
            'nano-banana-pro'
        );

        Process::assertRan(function ($process) {
            $c = $this->cmdString($process);

            return str_contains($c, '--aspect 3:4')
                && ! str_contains($c, '4:5');
        });
    }

    public function test_unknown_aspect_omits_the_flag(): void
    {
        // A ratio not in the enum and not remappable → drop --aspect entirely
        // (client default) rather than blow up the submit.
        Process::fake(['*' => Process::result(output: 'submitted: uuid=u1 status=1')]);

        app(GeminiGenClientBridge::class)->submit(
            'generate_image',
            ['prompt' => 'a cat', 'aspect_ratio' => '7:11'],
            [],
            'nano-banana-pro'
        );

        Process::assertRan(fn ($process) => ! str_contains($this->cmdString($process), '--aspect'));
    }

    public function test_non_enum_style_cinematic_is_remapped_to_photorealistic(): void
    {
        // Blog segments author style "Cinematic"; the CLI --style enum has no
        // Cinematic and argparse-exit-2's. Bridge must remap → Photorealistic.
        Process::fake(['*' => Process::result(output: 'submitted: uuid=u1 status=1')]);

        app(GeminiGenClientBridge::class)->submit(
            'generate_image',
            ['prompt' => 'a cat', 'aspect_ratio' => '16:9', 'style' => 'Cinematic'],
            [],
            'nano-banana-pro'
        );

        Process::assertRan(function ($process) {
            $c = $this->cmdString($process);

            return str_contains($c, '--style Photorealistic')
                && ! str_contains($c, 'Cinematic');
        });
    }

    public function test_unknown_style_omits_the_flag(): void
    {
        Process::fake(['*' => Process::result(output: 'submitted: uuid=u1 status=1')]);

        app(GeminiGenClientBridge::class)->submit(
            'generate_image',
            ['prompt' => 'a cat', 'aspect_ratio' => '1:1', 'style' => 'Vaporwave'],
            [],
            'nano-banana-pro'
        );

        Process::assertRan(fn ($process) => ! str_contains($this->cmdString($process), '--style'));
    }

    public function test_gpt_image_2_uses_mode_and_materializes_url_ref_to_local(): void
    {
        Http::fake(['*' => Http::response('PNGBYTES', 200)]);
        Process::fake(['*' => Process::result(output: 'submitted: uuid=g1 status=1')]);

        app(GeminiGenClientBridge::class)->submit(
            'imagen/gpt-image-2',
            ['prompt' => 'infographic', 'aspect_ratio' => '3:4', 'mode' => 'high'],
            ['https://cdn/face.png'],
            'gpt-image-2'
        );

        Process::assertRan(function ($process) {
            $c = $this->cmdString($process);

            return str_contains($c, '--mode high')
                // URL ref replaced by a local temp file — never the http url
                && ! str_contains($c, 'https://cdn/face.png')
                && str_contains($c, 'ggref_');
        });
    }

    public function test_submit_throws_on_cli_error(): void
    {
        Process::fake([
            '*' => Process::result(output: '', errorOutput: 'Error: model rejected', exitCode: 1),
        ]);

        $this->expectException(GeminiGenClientException::class);

        app(GeminiGenClientBridge::class)->submit(
            'generate_image',
            ['prompt' => 'x', 'aspect_ratio' => '1:1'],
            [],
            'nano-banana-pro'
        );
    }

    public function test_submit_throws_when_no_uuid_in_output(): void
    {
        Process::fake(['*' => Process::result(output: 'completed but weird output')]);

        $this->expectException(GeminiGenClientException::class);

        app(GeminiGenClientBridge::class)->submit(
            'generate_image',
            ['prompt' => 'x', 'aspect_ratio' => '1:1'],
            [],
            'nano-banana-pro'
        );
    }

    public function test_ssh_driver_builds_ssh_command_with_escaped_remote(): void
    {
        config(['geminigen.client_driver' => 'ssh']);
        Process::fake(['*' => Process::result(output: 'submitted: uuid=s1 status=1')]);

        app(GeminiGenClientBridge::class)->submit(
            'generate_image',
            ['prompt' => 'a cat', 'aspect_ratio' => '16:9'],
            [],
            'nano-banana-pro'
        );

        Process::assertRan(function ($process) {
            $cmd = $process->command;
            $this->assertIsArray($cmd);

            return $cmd[0] === 'ssh'
                && in_array('-o', $cmd, true)
                && in_array('BatchMode=yes', $cmd, true)
                // remote payload carries the escaped module + subcommand
                && str_contains(end($cmd), 'scripts.geminigen_image')
                && str_contains(end($cmd), '&&');
        });
    }
}
