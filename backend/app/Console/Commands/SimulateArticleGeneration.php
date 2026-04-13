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
                'en' => [
                    'title' => $this->generateOptimalTitle($idea->title, 'en'),
                    'content' => $this->generateSampleArticle($idea->title, 'en'),
                    'word_count' => 304,
                ],
                'id' => [
                    'title' => $this->generateOptimalTitle($idea->title, 'id'),
                    'content' => $this->generateSampleArticle($idea->title, 'id'),
                    'word_count' => 296,
                ],
                'target_keyword' => $this->extractKeyword($idea->title),
                'framework' => 'PASO',
                'hook_type' => 'Story',
                'hook_boost' => '+55%',
                'emotional_arc' => 'Discovery',
                'citation_count' => 2,
                'image_count' => 3,

                // Backward-compat flat scores
                'quality_score' => 8,
                'virality_score_value' => 4,
                'seo_score' => 5,

                // Full scoring gates (matches plugin completion callback schema)
                'seo_analysis' => [
                    'score' => 5,
                    'max_score' => 6,
                    'pass' => true,
                    'keyword' => $this->extractKeyword($idea->title),
                    'metrics' => [
                        'title_length' => ['value' => 52, 'status' => 'good'],
                        'keyword_in_title' => ['value' => true, 'status' => 'good'],
                        'title_word_count' => ['value' => 9, 'status' => 'good'],
                        'body_keyword_density' => ['value' => 1.2, 'status' => 'good'],
                        'keyword_in_first_100' => ['value' => true, 'status' => 'good'],
                        'keyword_in_headings' => ['value' => 2, 'status' => 'good'],
                    ],
                ],
                'quality_gate' => [
                    'score' => 8,
                    'max_score' => 10,
                    'pass' => true,
                    'criteria' => [
                        'clear' => ['pass' => true, 'evidence' => 'Grade 5 readability, short sentences, active voice'],
                        'concise' => ['pass' => true, 'evidence' => '22% fluff reduced in style pass'],
                        'compelling' => ['pass' => true, 'evidence' => 'Story hook with +55% engagement boost'],
                        'credible' => ['pass' => true, 'evidence' => '5 citations from McKinsey, Stanford HAI'],
                        'nested_loops' => ['pass' => true, 'evidence' => '3 open loops in first 500 words'],
                        'bucket_brigades' => ['pass' => true, 'evidence' => '4 bucket brigades placed at retention dips'],
                        'emotional_arc' => ['pass' => true, 'evidence' => 'Discovery arc: Curiosity → Insight → Empowerment'],
                        'scannability' => ['pass' => true, 'evidence' => 'H2/H3 hierarchy, numbered list, bold keywords'],
                        'benefit_first' => ['pass' => false, 'evidence' => 'Could strengthen opening value proposition'],
                        'dual_cta' => ['pass' => false, 'evidence' => 'Has primary CTA but secondary is weak'],
                    ],
                ],
                'virality_score' => [
                    'score' => 4,
                    'max_score' => 5,
                    'pass' => true,
                    'triggers' => [
                        'social_currency' => ['pass' => true, 'evidence' => 'Insider framework gives readers exclusive knowledge to share'],
                        'high_arousal_emotion' => ['pass' => true, 'evidence' => 'Failure story triggers anxiety, framework provides relief'],
                        'practical_utility' => ['pass' => true, 'evidence' => '7-step actionable framework with specific timeframe'],
                        'identity_signaling' => ['pass' => true, 'evidence' => '"If you\'re the kind of professional who..." language'],
                        'cognitive_gap_closure' => ['pass' => false, 'evidence' => 'Gap opened but resolution could be more satisfying'],
                    ],
                ],

                // Image count: ~300 words = short (1 cover + 2 inline = 3 total)
                'image_prompts' => [
                    [
                        'type' => 'cover',
                        'section' => null,
                        'concept' => 'Cover: Futuristic AI workspace with holographic interfaces',
                        'prompt' => 'A cinematic wide shot of a futuristic workspace with holographic AI interfaces floating in the air, warm golden light streaming through large windows, dark moody atmosphere with cyan accent lighting, no text no words no letters',
                        'model' => 'nano-banana-pro',
                        'style' => 'Portrait Cinematic',
                        'aspect_ratio' => '16:9',
                        'resolution' => '2K',
                        'placement' => 'Article header / social share thumbnail',
                        'suggested_position' => 0,
                    ],
                    [
                        'type' => 'inline',
                        'section' => 'The one metric that predicted our failure',
                        'concept' => 'Problem: Team struggling with failed AI implementation',
                        'prompt' => 'A dark tense office scene with a stressed team staring at screens showing declining metrics and red charts, cool blue lighting casting long shadows, isolation and frustration visible, cinematic mood, no text no words',
                        'model' => 'nano-banana-pro',
                        'style' => 'Cinematic',
                        'aspect_ratio' => '16:9',
                        'resolution' => '1K',
                        'placement' => 'After the failure story section',
                        'suggested_position' => 5,
                    ],
                    [
                        'type' => 'inline',
                        'section' => '7 fastest ways to align your AI strategy',
                        'concept' => 'Solution: Bright team collaborating on AI strategy',
                        'prompt' => 'A warm bright modern office with a diverse team collaborating around a large screen showing an AI workflow diagram, natural light flooding in, open space with plants, forward momentum and optimism, no text no words',
                        'model' => 'nano-banana-pro',
                        'style' => 'Photorealistic',
                        'aspect_ratio' => '16:9',
                        'resolution' => '1K',
                        'placement' => 'After the 7-step framework list',
                        'suggested_position' => 9,
                    ],
                ],
                'sources' => [
                    ['name' => 'McKinsey Global AI Survey 2025', 'url' => 'https://www.mckinsey.com/capabilities/quantumblack/our-insights/the-state-of-ai'],
                    ['name' => 'Stanford HAI AI Index Report 2025', 'url' => 'https://aiindex.stanford.edu/report/'],
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

    private function generateOptimalTitle(string $original, string $lang): string
    {
        if ($lang === 'id') {
            return '7 Cara AI Mengubah Bisnis dalam 30 Hari ke Depan';
        }
        $words = explode(' ', $original);
        $short = implode(' ', array_slice($words, 0, 9));
        return strlen($short) > 60 ? substr($short, 0, 57) . '...' : $short;
    }

    private function extractKeyword(string $title): string
    {
        $lower = strtolower($title);
        foreach (['ai strategy', 'artificial intelligence', 'machine learning', 'ai'] as $kw) {
            if (str_contains($lower, $kw)) {
                return $kw;
            }
        }
        $words = explode(' ', $title);
        return strtolower(implode(' ', array_slice($words, 0, 2)));
    }

    private function generateSampleArticle(string $title, string $lang = 'en'): string
    {
        if ($lang === 'id') {
            return "<p>Sesuatu yang memuaskan baru saja terjadi.</p>
<p>Anda membuka artikel ini mengharapkan saran yang biasa — tips yang sama yang didaur ulang dari 2019, didandani dengan tanggal baru dan foto stok seseorang menunjuk papan tulis.</p>
<p>Tapi inilah masalahnya:</p>
<p>Yang akan Anda baca ini berbeda. Menurut <a href='https://www.mckinsey.com/capabilities/quantumblack/our-insights/the-state-of-ai'>Survei AI Global McKinsey 2025</a>, 72% organisasi sekarang menerapkan AI di setidaknya satu fungsi bisnis — naik dari hanya 20% lima tahun lalu. Akselerasinya tidak melambat. Malah semakin cepat.</p>
<h3>Satu metrik yang memprediksi kegagalan kami</h3>
<p>Sebelum kita masuk ke kerangka kerja yang bisa langsung diterapkan, izinkan saya menceritakan kesalahan yang menghabiskan tiga bulan kerja sia-sia bagi tim kami.</p>
<p>Kami sedang membangun apa yang kami pikir adalah sistem sempurna. Setiap fitur dipoles. Setiap kasus tepi ditangani. Setiap tes lulus dengan sempurna.</p>
<p>Dan kemudian semuanya berubah:</p>
<p>Pengguna kami tidak peduli dengan 90% dari apa yang kami bangun. <a href='https://aiindex.stanford.edu/report/'>Laporan Stanford HAI AI Index</a> mengkonfirmasi apa yang kami curigai — sebagian besar implementasi AI gagal bukan karena keterbatasan teknis, tetapi karena prioritas yang tidak selaras.</p>
<h3>7 cara tercepat menyelaraskan strategi AI Anda dalam 30 hari</h3>
<p>Inilah kerangka kerja praktis yang benar-benar berhasil:</p>
<ol>
<li><strong>Audit alur kerja Anda saat ini</strong> — identifikasi 3 titik gesekan tertinggi</li>
<li><strong>Petakan pola keputusan</strong> — temukan di mana manusia membuat pilihan berulang</li>
<li><strong>Mulai dengan augmentasi</strong> — bantu manusia sebelum menggantikan mereka</li>
<li><strong>Ukur time-to-value</strong> — lacak minggu, bukan bulan, untuk dampak pertama</li>
<li><strong>Bangun feedback loops</strong> — biarkan sistem belajar dari koreksi</li>
<li><strong>Dokumentasikan pengetahuan tribal</strong> — tangkap apa yang diketahui para ahli secara intuitif</li>
<li><strong>Kirim mingguan</strong> — iterasi kecil mengalahkan peluncuran besar setiap saat</li>
</ol>
<h3>Apa artinya ini untuk Anda</h3>
<p>Jika Anda adalah jenis profesional yang membaca artikel seperti ini — seseorang yang selalu selangkah lebih maju, yang mengimplementasikan sebelum kompetitor bahkan menyadari perubahannya — maka Anda sudah tahu apa yang harus dilakukan selanjutnya.</p>
<p>Pertanyaannya bukan apakah AI akan mentransformasi industri Anda. Pertanyaannya adalah apakah Anda yang akan memimpin transformasi itu, atau berebut untuk mengejar ketertinggalan.</p>";
        }

        return "<p>Something satisfying just happened.</p>
<p>You opened this article expecting the usual rehashed advice — the same tips recycled from 2019, dressed up with a new date and a stock photo of someone pointing at a whiteboard.</p>
<p>But here's the thing:</p>
<p>What you're about to read isn't that. According to a <a href='https://www.mckinsey.com/capabilities/quantumblack/our-insights/the-state-of-ai'>2025 McKinsey Global AI Survey</a>, 72% of organizations now deploy AI in at least one business function — up from just 20% five years ago. The acceleration isn't slowing down. It's compounding.</p>
<h3>The one metric that predicted our failure</h3>
<p>Before we dive into the actionable framework, let me tell you about a mistake that cost our team three months of wasted effort.</p>
<p>We were building what we thought was the perfect system. Every feature polished. Every edge case handled. Every test passing with flying colors.</p>
<p>And then everything changed:</p>
<p>Our users didn't care about 90% of what we built. The <a href='https://aiindex.stanford.edu/report/'>Stanford HAI AI Index Report</a> confirmed what we suspected — most AI implementations fail not from technical limitations, but from misaligned priorities.</p>
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
<p>The question isn't whether AI will transform your industry. The question is whether you'll be the one leading the transformation, or scrambling to catch up.</p>";
    }
}
