<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;

/**
 * Idempotent seeder for the 4 LinkedIn-sourced recommendations that
 * power the homepage Testimonials carousel (Phase 6 partial — May 5).
 *
 * Source: linkedin.com/in/alisadikinma/details/recommendations/
 * Date captured: 2026-05-05 (operator screenshot).
 *
 * Idempotent — uses updateOrCreate keyed on (client_name, source) so
 * re-running this seeder does not duplicate rows. To refresh content
 * without changing the row identity, edit the values below and re-run
 * `php artisan db:seed --class=LinkedInTestimonialsSeeder`.
 */
class LinkedInTestimonialsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'client_name' => 'Galuh Koco Sadewo',
                'company_name' => 'BOTIKA',
                'job_title' => 'Co-Founder · Chief Business Dev & Partnership',
                'testimonial_text' => "I had the opportunity to grow alongside Ali Sadikin, MA as a fellow startup founder in the Indonesian ecosystem, particularly through our journey as Telkomsel NextDev alumni. Working in parallel and learning side by side, I witnessed firsthand Ali's persistence, leadership, and long-term commitment to building meaningful technology solutions.\n\nAli demonstrated strong founder-level leadership in steering Marlin Booking from an early-stage startup into a platform that successfully supported the Indonesian government in digitizing passenger port operations at a national scale. His ability to translate complex operational and institutional challenges into practical, scalable digital solutions reflects both strategic clarity and execution discipline.\n\nAs a fellow founder, I deeply respect Ali's work ethic, resilience under pressure, and his ability to lead and delegate effectively while maintaining focus on impact. He is a trusted partner and a credible technology leader, and I wholeheartedly recommend him for leadership, strategic, and transformation-focused roles that demand vision, perseverance, and execution excellence.",
                'source' => 'linkedin',
                'source_url' => 'https://www.linkedin.com/in/alisadikinma/details/recommendations/',
                'star_rating' => 5,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'client_name' => 'Andria Gutama',
                'company_name' => 'Digital Operation Transformation (former direct report)',
                'job_title' => 'IT Manager · Full Stack Web Developer · Database Engineer · API Integration · Data Analyst',
                'testimonial_text' => "It is an honor to recommend Pak Ali Sadikin, with whom I had the privilege of working closely in the Digital Operation Transformation (DOT) department. Pak Ali is an exceptional leader and a highly respected expert in Artificial Intelligence. His strategic thinking, clarity in direction, and deep technical insight have been instrumental in shaping the foundation of our department.\n\nOn a personal level, I have always appreciated his professionalism, integrity, and genuine kindness. Despite his extensive expertise, he remains approachable and consistently willing to guide and support the team. His ability to connect with people — while maintaining high standards of excellence — has created an environment where collaboration truly thrives.\n\nWorking alongside him to build and strengthen the DOT department has been one of the most meaningful experiences in my career. Pak Ali's dedication, steady leadership, and thoughtful mentorship have left a lasting impact on both the team and myself. I am confident that any organization will greatly benefit from his presence and vision.",
                'source' => 'linkedin',
                'source_url' => 'https://www.linkedin.com/in/alisadikinma/details/recommendations/',
                'star_rating' => 5,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'client_name' => 'Jairo Launio',
                'company_name' => 'Advantive (former teammate at MPA Singapore)',
                'job_title' => 'Senior Software Engineer',
                'testimonial_text' => "I had the pleasure of working with Ali during our time at MPA in Singapore, where we collaborated closely on several projects. From day one, he consistently demonstrated strong work ethic, dedication, and a genuine commitment to delivering high-quality results.\n\nAli was an exceptional team player, always proactive, reliable, and ready to support others to ensure project success. I was particularly impressed by his ability to stay organized under pressure and meet deadlines without compromising quality.\n\nBeyond technical skills and project management capabilities, Ali brought a positive attitude that made working together both productive and enjoyable. I have no doubt that he will bring the same level of professionalism, integrity, and excellence to any organization he joins.",
                'source' => 'linkedin',
                'source_url' => 'https://www.linkedin.com/in/alisadikinma/details/recommendations/',
                'star_rating' => 5,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'client_name' => 'Megawati Megawati',
                'company_name' => 'Six Seconds EQCC (former senior at Satnusa)',
                'job_title' => 'Master of Business Administration · ICF Associate Certified Coach',
                'testimonial_text' => "I had the opportunity to work with Ali during two important phases at Satnusa. He managed the ISC Project Integration for XIAOMI, which was complex and required coordination across multiple teams. Despite the challenges, he handled the project with steady communication and a structured approach.\n\nAs Head of Digital Transformation 4.0, Ali oversaw initiatives such as the MySatnusa Super App, our first AI implementation, and RPA deployment across several departments. These projects helped improve internal efficiency and modernize some of our processes.\n\nAli is consistent, responsible, and able to collaborate well with different functions. His contributions supported Satnusa's progress in system integration and digital transformation.",
                'source' => 'linkedin',
                'source_url' => 'https://www.linkedin.com/in/alisadikinma/details/recommendations/',
                'star_rating' => 5,
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($rows as $row) {
            Testimonial::updateOrCreate(
                [
                    'client_name' => $row['client_name'],
                    'source' => $row['source'],
                ],
                $row
            );
        }
    }
}
