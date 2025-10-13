<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestimonialsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $testimonials = [
            [
                'client_name' => 'Sarah Johnson',
                'company_name' => 'Tech Innovators Inc.',
                'job_title' => 'CEO',
                'testimonial_text' => '<p>Working with this team was an absolute pleasure. They delivered our project <strong>ahead of schedule</strong> and exceeded all our expectations. The attention to detail and commitment to excellence was outstanding.</p>',
                'client_photo' => 'testimonials/sarah-johnson.jpg',
                'star_rating' => 5,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'client_name' => 'Michael Chen',
                'company_name' => 'Digital Solutions Ltd.',
                'job_title' => 'CTO',
                'testimonial_text' => '<p>The technical expertise and innovative approach brought to our project was <em>exceptional</em>. They transformed our legacy system into a modern, scalable solution that has significantly improved our operations.</p>',
                'client_photo' => 'testimonials/michael-chen.jpg',
                'star_rating' => 5,
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'client_name' => 'Emily Rodriguez',
                'company_name' => 'Creative Minds Agency',
                'job_title' => 'Creative Director',
                'testimonial_text' => '<p>I was impressed by the creativity and professionalism throughout the entire project. The design was <strong>pixel-perfect</strong> and the user experience was intuitive and delightful.</p>',
                'client_photo' => 'testimonials/emily-rodriguez.jpg',
                'star_rating' => 4,
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'client_name' => 'David Thompson',
                'company_name' => 'E-Commerce Ventures',
                'job_title' => 'Founder',
                'testimonial_text' => '<p>Our e-commerce platform saw a <strong>300% increase in conversion rates</strong> after the redesign. The team\'s understanding of user behavior and market trends was remarkable.</p>',
                'client_photo' => 'testimonials/david-thompson.jpg',
                'star_rating' => 5,
                'is_active' => false,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'client_name' => 'Amanda Williams',
                'company_name' => 'Global Finance Corp',
                'job_title' => 'VP of Technology',
                'testimonial_text' => '<p>The security measures and best practices implemented in our financial application were <em>top-notch</em>. We appreciate the proactive communication and timely updates throughout the development process.</p>',
                'client_photo' => 'testimonials/amanda-williams.jpg',
                'star_rating' => 4,
                'is_active' => true,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('testimonials')->insert($testimonials);
    }
}
