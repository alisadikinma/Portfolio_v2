<?php

namespace Tests\Feature;

use App\Console\Commands\PollRebrandAssets;
use App\Jobs\GenerateRebrandAssets;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Models\Setting;
use App\Services\EntityReferenceService;
use App\Services\GeminiGenVideoService;
use App\Services\VideoHookSceneAuthor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase C (#1 topic-aware hook) — the hook scene author picks a topic-relevant
 * public figure (by NAME, used only to fetch a face ref), authors a topic scene
 * that never contains the name, GenerateRebrandAssets dispatches the keyframe with
 * [creatorFace, figureFace], and a PROMINENT_PEOPLE_UPLOAD refusal degrades to a
 * creator-only retry (figure ref dropped) instead of failing.
 *
 * See docs/plans/2026-06-13-video-rebrand-quality-pass.md Phase C.
 */
class HookAuthorAndFallbackTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string,mixed> $cannedParsed */
    private function fakeAuthor(array $cannedParsed): VideoHookSceneAuthor
    {
        return new class($cannedParsed) extends VideoHookSceneAuthor {
            public function __construct(private array $cannedParsed)
            {
            }

            protected function runHookAuthor(string $prompt): array
            {
                return ['success' => true, 'parsed' => $this->cannedParsed, 'output' => '', 'error' => null, 'repaired' => false];
            }
        };
    }

    public function test_author_returns_figure_name_and_scene(): void
    {
        $svc = $this->fakeAuthor([
            'figure_name' => 'Sundar Pichai',
            'scene_prompt' => 'Creator on the left and the person matching reference image 2 on the right in a Google campus garden.',
        ]);

        $res = $svc->author('AI Tools, Stitch, Gemini', true);

        $this->assertTrue($res['success']);
        $this->assertSame('Sundar Pichai', $res['figure_name']);
        $this->assertStringContainsString('reference image 2', $res['scene_prompt']);
    }

    public function test_sanitize_strips_figure_name_from_scene(): void
    {
        $svc = new VideoHookSceneAuthor();

        $out = $svc->sanitizeScene("Creator having coffee with Sundar Pichai, and Pichai smiles warmly.", 'Sundar Pichai');

        $this->assertStringNotContainsStringIgnoringCase('Sundar', $out);
        $this->assertStringNotContainsStringIgnoringCase('Pichai', $out);
        $this->assertStringContainsString('reference image 2', $out);
    }

    public function test_author_creator_only_ignores_figure_when_not_allowed(): void
    {
        $svc = $this->fakeAuthor([
            'figure_name' => 'Sundar Pichai',
            'scene_prompt' => 'A striking solo portrait of the creator on a modern tech campus.',
        ]);

        $res = $svc->author('AI Tools', false);

        $this->assertTrue($res['success']);
        $this->assertNull($res['figure_name']);
    }

    private function jobWithTools(): RepurposeJob
    {
        Setting::create(['group' => 'about', 'key' => 'profile_photo', 'value' => 'https://cdn/face.jpg']);
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'extracted']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool', 'header_title' => 'Gemini']);

        return $job;
    }

    public function test_hook_dispatch_carries_creator_plus_figure_refs(): void
    {
        $job = $this->jobWithTools();

        $this->mock(VideoHookSceneAuthor::class, function ($m) {
            $m->shouldReceive('author')->andReturn(['success' => true, 'figure_name' => 'Sundar Pichai', 'scene_prompt' => 'creator left, reference image 2 right', 'error' => null]);
        });
        $this->mock(EntityReferenceService::class, function ($m) {
            $m->shouldReceive('findOrFetch')->with('Sundar Pichai', 'person')->andReturn(['url' => 'https://cdn/pichai.jpg']);
        });

        $captured = [];
        $this->mock(GeminiGenVideoService::class, function ($m) use (&$captured) {
            $m->shouldReceive('dispatchKeyframe')->andReturnUsing(function ($refs, $prompt, $ctx) use (&$captured) {
                $captured[] = $refs;

                return 'kf-' . count($captured);
            });
        });

        (new GenerateRebrandAssets($job->id))->handle(app(GeminiGenVideoService::class));

        // First dispatch is the hook (built before the CTA).
        $this->assertSame(['https://cdn/face.jpg', 'https://cdn/pichai.jpg'], $captured[0], 'hook keyframe must carry creator + figure refs');
        // CTA is creator-only.
        $this->assertSame(['https://cdn/face.jpg'], $captured[1]);
    }

    public function test_figure_dropped_hook_dispatches_creator_only(): void
    {
        $job = $this->jobWithTools();
        // Pre-create the hook with the safety sentinel set (as the poll fallback would).
        RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook',
            'composited_status' => 'pending', 'figure_dropped' => true,
        ]);

        $sawAllowFigure = null;
        $this->mock(VideoHookSceneAuthor::class, function ($m) use (&$sawAllowFigure) {
            $m->shouldReceive('author')->andReturnUsing(function ($topic, $allowFigure) use (&$sawAllowFigure) {
                $sawAllowFigure = $allowFigure;

                return ['success' => true, 'figure_name' => null, 'scene_prompt' => 'solo creator portrait', 'error' => null];
            });
        });
        // EntityReferenceService must NOT be called when the figure is dropped.
        $this->mock(EntityReferenceService::class, function ($m) {
            $m->shouldReceive('findOrFetch')->never();
        });

        $captured = [];
        $this->mock(GeminiGenVideoService::class, function ($m) use (&$captured) {
            $m->shouldReceive('dispatchKeyframe')->andReturnUsing(function ($refs, $prompt, $ctx) use (&$captured) {
                $captured[] = $refs;

                return 'kf-' . count($captured);
            });
        });

        (new GenerateRebrandAssets($job->id))->handle(app(GeminiGenVideoService::class));

        $this->assertFalse($sawAllowFigure, 'figure_dropped sentinel must force allowFigure=false');
        $this->assertSame(['https://cdn/face.jpg'], $captured[0], 'dropped-figure hook must be creator-only (1 ref)');
    }

    public function test_poll_marks_figure_dropped_on_hook_safety_refusal(): void
    {
        config()->set('services.geminigen.api_key', 'test-key');

        Http::fake([
            '*/history/*' => Http::response([
                'status' => 3,
                'error_code' => 'PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD',
                'error_message' => 'We do not allow uploading images of prominent people.',
            ], 200),
        ]);

        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'generating_assets']);
        $hook = RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook',
            'keyframe_status' => 'generating', 'keyframe_job_uuid' => 'kf-uuid-1',
            'composited_status' => 'pending', 'figure_dropped' => false,
        ]);

        $this->artisan('repurpose:poll-rebrand-assets')->assertExitCode(0);

        $hook->refresh();
        $this->assertSame('failed', $hook->keyframe_status);
        $this->assertTrue($hook->figure_dropped, 'a PROMINENT_PEOPLE_UPLOAD refusal must set the figure_dropped sentinel');
    }

    /**
     * Defect (b): the author picks a figure but its photo fails to resolve (Wikidata
     * miss OR storage write conflict). The figure-authored scene references "image 2"
     * — shipping it with no ref-2 leaves a dangling celebrity reference that trips the
     * prominent-people filter. The job must RE-AUTHOR creator-only + persist the
     * sentinel, never dispatch the figure scene.
     */
    public function test_hook_unresolved_figure_reauthors_creator_only_and_sets_sentinel(): void
    {
        $job = $this->jobWithTools();

        $calls = [];
        $this->mock(VideoHookSceneAuthor::class, function ($m) use (&$calls) {
            $m->shouldReceive('author')->andReturnUsing(function ($topic, $allowFigure) use (&$calls) {
                $calls[] = $allowFigure;

                return $allowFigure
                    ? ['success' => true, 'figure_name' => 'Sundar Pichai', 'scene_prompt' => 'creator left, reference image 2 right', 'error' => null]
                    : ['success' => true, 'figure_name' => null, 'scene_prompt' => 'solo creator portrait on a tech campus', 'error' => null];
            });
        });
        // Figure photo unresolvable.
        $this->mock(EntityReferenceService::class, function ($m) {
            $m->shouldReceive('findOrFetch')->with('Sundar Pichai', 'person')->andReturnNull();
        });

        $captured = [];
        $this->mock(GeminiGenVideoService::class, function ($m) use (&$captured) {
            $m->shouldReceive('dispatchKeyframe')->andReturnUsing(function ($refs, $prompt, $ctx) use (&$captured) {
                $captured[] = ['refs' => $refs, 'prompt' => $prompt];

                return 'kf-' . count($captured);
            });
        });

        (new GenerateRebrandAssets($job->id))->handle(app(GeminiGenVideoService::class));

        $this->assertSame([true, false], $calls, 'must re-author creator-only after the figure fails to resolve');
        $this->assertSame(['https://cdn/face.jpg'], $captured[0]['refs'], 'hook must be creator-only (no dangling figure ref)');
        $this->assertStringContainsString('solo creator portrait', $captured[0]['prompt'], 'hook must use the clean creator-only scene');

        $hook = RepurposeVideoSlide::where('repurpose_job_id', $job->id)->where('role', 'hook')->first();
        $this->assertTrue((bool) $hook->figure_dropped, 'unresolved figure must persist the sentinel so recovery skips the failing fetch');
    }

    /**
     * Defect (c): Veo can refuse a hook clip whose keyframe shows a recognizable
     * public figure (PROMINENT_PEOPLE_FILTER_FAILED) even though the keyframe itself
     * passed image-gen. The VEO-stage failure (not just the keyframe stage) must set
     * figure_dropped so recover() re-authors creator-only instead of looping forever.
     */
    public function test_poll_marks_figure_dropped_on_hook_veo_safety_refusal(): void
    {
        config()->set('services.geminigen.api_key', 'test-key');

        Http::fake([
            '*/history/*' => Http::response([
                'status' => 3,
                'error_code' => 'PUBLIC_ERROR_PROMINENT_PEOPLE_FILTER_FAILED',
                'error_message' => 'The prompt contains words that describe characteristics related to a celebrity.',
            ], 200),
        ]);

        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'generating_assets']);
        $hook = RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook',
            'keyframe_status' => 'done', 'keyframe_url' => 'https://cdn/kf.jpg',
            'veo_status' => 'generating', 'veo_job_uuid' => 'veo-uuid-1',
            'composited_status' => 'pending', 'figure_dropped' => false,
        ]);

        $this->artisan('repurpose:poll-rebrand-assets')->assertExitCode(0);

        $hook->refresh();
        $this->assertSame('failed', $hook->veo_status);
        $this->assertTrue($hook->figure_dropped, 'a VEO-stage prominent-people refusal must set figure_dropped for the creator-only retry');
    }
}
