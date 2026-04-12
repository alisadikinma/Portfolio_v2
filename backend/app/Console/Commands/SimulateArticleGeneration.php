<?php

namespace App\Console\Commands;

use App\Models\ContentIdea;
use Illuminate\Console\Command;

class SimulateArticleGeneration extends Command
{
    protected $signature = 'article:simulate {ideaId}';
    protected $description = 'Simulate article generation progress (for local testing)';

    private array $steps = [
        ['step' => 'input_collection', 'percentage' => 5, 'message' => 'Input parsed from CLI flags', 'delay' => 2],
        ['step' => 'topic_research', 'percentage' => 10, 'message' => 'Topic research completed — 4 sources found', 'delay' => 3],
        ['step' => 'framework_selection', 'percentage' => 15, 'message' => 'Framework selected: PASO (Problem-Agitate-Solution-Outcome)', 'delay' => 2],
        ['step' => 'emotional_arc', 'percentage' => 20, 'message' => 'Emotional arc mapped: Discovery (Curiosity → Insight → Empowerment)', 'delay' => 2],
        ['step' => 'hook_generation', 'percentage' => 25, 'message' => 'Hook generated: Story hook (+55% engagement)', 'delay' => 2],
        ['step' => 'outline_generation', 'percentage' => 35, 'message' => 'Full outline created — 7 sections, 4 images planned', 'delay' => 3],
        ['step' => 'article_writing', 'percentage' => 70, 'message' => 'Article draft completed — 2,150 words, 5 citations', 'delay' => 8],
        ['step' => 'style_pass', 'percentage' => 80, 'message' => 'Style editing done — 22% fluff reduced, 0 forbidden words', 'delay' => 3],
        ['step' => 'image_prompts', 'percentage' => 85, 'message' => 'Image prompts generated — 1 cover + 3 inline images', 'delay' => 2],
        ['step' => 'virality_score', 'percentage' => 90, 'message' => 'Virality score: 4/5 (PASS)', 'delay' => 2],
        ['step' => 'quality_gate', 'percentage' => 95, 'message' => 'Quality gate: 8/10 (PASS)', 'delay' => 2],
    ];

    public function handle(): int
    {
        $idea = ContentIdea::find($this->argument('ideaId'));
        if (!$idea) {
            $this->error("Content idea not found.");
            return 1;
        }

        if ($idea->status !== 'researching') {
            $this->error("Idea status is '{$idea->status}', expected 'researching'.");
            $this->info("Set it to researching first via the admin panel (click Next → Configure → Start Research).");
            return 1;
        }

        $this->info("Simulating article generation for: {$idea->title}");
        $this->newLine();

        foreach ($this->steps as $step) {
            sleep($step['delay']);

            $logEntry = [
                'timestamp' => now()->toISOString(),
                'step' => $step['step'],
                'percentage' => $step['percentage'],
                'message' => $step['message'],
            ];

            $idea->update([
                'progress_percentage' => $step['percentage'],
                'current_step' => $step['step'],
                'progress_log' => array_merge($idea->progress_log ?? [], [$logEntry]),
            ]);

            $this->info("[{$step['percentage']}%] {$step['step']} — {$step['message']}");
        }

        // Simulate completion
        sleep(2);
        $idea->update([
            'status' => 'article_ready',
            'progress_percentage' => 100,
            'current_step' => 'completed',
            'generated_article' => [
                'title' => $idea->title,
                'content' => $this->generateSampleArticle($idea->title),
                'word_count' => 2150,
                'quality_score' => 8,
                'virality_score' => 4,
                'framework' => 'PASO',
                'hook_type' => 'Story',
                'emotional_arc' => 'Discovery',
                'image_prompts' => [
                    [
                        'concept' => 'Hero visual representing the core theme',
                        'prompt' => 'A cinematic wide shot of a futuristic workspace with holographic AI interfaces floating in the air, warm golden light streaming through large windows, dark moody atmosphere with cyan accent lighting',
                        'model' => 'nano-banana-pro',
                        'style' => 'Cinematic',
                        'aspect_ratio' => '16:9',
                        'resolution' => '2K',
                        'placement' => 'Article header / social share thumbnail',
                    ],
                ],
                'sources' => [
                    ['title' => 'McKinsey Global AI Survey 2025', 'url' => 'https://mckinsey.com/ai-survey-2025'],
                    ['title' => 'Stanford HAI AI Index Report', 'url' => 'https://aiindex.stanford.edu/report'],
                ],
            ],
            'progress_log' => array_merge($idea->progress_log ?? [], [[
                'timestamp' => now()->toISOString(),
                'step' => 'completed',
                'percentage' => 100,
                'message' => 'Article generation completed successfully',
            ]]),
        ]);

        $this->newLine();
        $this->info("[100%] completed — Article generation completed successfully");
        $this->newLine();
        $this->info("Done! Idea #{$idea->id} is now 'article_ready'. Check the admin panel.");

        return 0;
    }

    private function generateSampleArticle(string $title): string
    {
        return "<h2>{$title}</h2>
<p>Something satisfying just happened.</p>
<p>You opened this article expecting the usual rehashed advice — the same tips recycled from 2019, dressed up with a new date and a stock photo of someone pointing at a whiteboard.</p>
<p>But here's the thing:</p>
<p>What you're about to read isn't that. According to a <a href='https://mckinsey.com/ai-survey-2025'>2025 McKinsey Global AI Survey</a>, 72% of organizations now deploy AI in at least one business function — up from just 20% five years ago. The acceleration isn't slowing down. It's compounding.</p>
<h3>The one metric that predicted our failure</h3>
<p>Before we dive into the actionable framework, let me tell you about a mistake that cost our team three months of wasted effort.</p>
<p>We were building what we thought was the perfect system. Every feature polished. Every edge case handled. Every test passing with flying colors.</p>
<p>And then everything changed:</p>
<p>Our users didn't care about 90% of what we built. The Stanford HAI AI Index Report confirmed what we suspected — most AI implementations fail not from technical limitations, but from misaligned priorities.</p>
<h3>7 fastest ways to align your AI strategy in 30 days</h3>
<p>Here's the practical framework that actually works:</p>
<ol>
<li><strong>Audit your current workflows</strong> — identify the 3 highest-friction touchpoints</li>
<li><strong>Map decision patterns</strong> — find where humans make repetitive choices</li>
<li><strong>Start with augmentation</strong> — assist humans before replacing them</li>
<li><strong>Measure time-to-value</strong> — track weeks, not months, to first impact</li>
<li><strong>Build feedback loops</strong> — let the system learn from corrections</li>
<li><strong>Document tribal knowledge</strong> — capture what experts know intuitively</li>
<li><strong>Ship weekly</strong> — small iterations beat big launches every time</li>
</ol>
<h3>What this means for you</h3>
<p>If you're the kind of professional who reads articles like this — someone who stays ahead of the curve, who implements before competitors even notice the shift — then you already know what to do next.</p>
<p>The question isn't whether AI will transform your industry. The question is whether you'll be the one leading the transformation, or scrambling to catch up.</p>
<p><strong>Ready to start?</strong> <a href='#'>Get the free AI Strategy Playbook</a> — the exact template we used to align three enterprise teams in under 30 days.</p>
<p><em>Not ready to commit?</em> <a href='#'>Subscribe to our weekly AI brief</a> — one actionable insight delivered every Tuesday morning.</p>";
    }
}
