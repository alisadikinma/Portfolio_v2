-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 23, 2025 at 02:00 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `portfolio_v2`
--

-- --------------------------------------------------------

--
-- Table structure for table `about`
--

CREATE TABLE `about` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `bio` longtext DEFAULT NULL,
  `profile_photo` varchar(500) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `website` varchar(500) DEFAULT NULL,
  `skills` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`skills`)),
  `experience` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`experience`)),
  `education` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`education`)),
  `social_links` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`social_links`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `about`
--

INSERT INTO `about` (`id`, `name`, `title`, `bio`, `profile_photo`, `email`, `phone`, `location`, `website`, `skills`, `experience`, `education`, `social_links`, `created_at`, `updated_at`) VALUES
(1, 'Ali Sadikin', 'AI Automation Engineer', 'Building AI automation to democratize viral content creation in Asia. Seefluencer alumni. Believe every creator deserves the tools to succeed. Currently connecting 500M+ aspiring creators with our viral intelligence platform. Let\'s engineer virality, not chase it.', NULL, NULL, NULL, NULL, NULL, '[{\"name\": \"Artificial Intelligence (AI)\", \"level\": \"Expert\"}, {\"name\": \"Content Creation\", \"level\": \"Advanced\"}, {\"name\": \"Product Management\", \"level\": \"Advanced\"}, {\"name\": \"Community Building\", \"level\": \"Expert\"}, {\"name\": \"Go-to-Market Strategy\", \"level\": \"Intermediate\"}]', NULL, NULL, '[{\"platform\": \"LinkedIn\", \"url\": \"https://linkedin.com/in/alisadikin\", \"icon\": \"linkedin\"}, {\"platform\": \"Twitter\", \"url\": \"https://twitter.com/alisadikin\", \"icon\": \"twitter\"}, {\"platform\": \"GitHub\", \"url\": \"https://github.com/alisadikin\", \"icon\": \"github\"}]', '2025-10-22 23:54:11', '2025-10-22 23:54:11');

-- --------------------------------------------------------

--
-- Table structure for table `automation_logs`
--

CREATE TABLE `automation_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `token_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `awards`
--

CREATE TABLE `awards` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `organization` varchar(255) NOT NULL,
  `credential_id` varchar(255) DEFAULT NULL,
  `credential_url` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `received_at` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `featured_gallery_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `awards`
--

INSERT INTO `awards` (`id`, `title`, `description`, `organization`, `credential_id`, `credential_url`, `image`, `received_at`, `is_active`, `sort_order`, `created_at`, `updated_at`, `featured_gallery_id`) VALUES
(1, 'Best Web Developer Award 2025', 'Recognized for outstanding achievement in modern web development, creating innovative and user-centric solutions using cutting-edge technologies.', 'International Web Development Association', 'IWDA-2024-001', 'https://example.com/credentials/iwda-2024-001', 'awards/web-developer-2024.jpg', '2024-06-15', 1, 1, '2025-10-14 19:03:20', '2025-10-15 01:49:10', NULL),
(2, 'Excellence in Full-Stack Development', 'Awarded for demonstrating exceptional skills in both frontend and backend technologies, with a focus on scalable architecture and clean code practices.', 'Tech Excellence Foundation', 'TEF-FULLSTACK-2023', 'https://example.com/credentials/tef-fullstack-2023', 'awards/fullstack-excellence.jpg', '2023-11-20', 1, 2, '2025-10-14 19:03:20', '2025-10-14 19:03:20', NULL),
(3, 'Innovation in Laravel Development', 'Honored for creating robust and maintainable Laravel applications with advanced features and best practices implementation.', 'Laravel Community Excellence Awards', 'LCEA-2023-042', 'https://example.com/credentials/lcea-2023-042', 'awards/laravel-innovation.jpg', '2023-09-10', 1, 3, '2025-10-14 19:03:20', '2025-10-14 19:03:20', NULL),
(4, 'Outstanding Vue.js Implementation', 'Recognized for building performant and elegant single-page applications using Vue 3 with modern composition API patterns.', 'Frontend Developers Guild', 'FDG-VUE-2023', 'https://example.com/credentials/fdg-vue-2023', 'awards/vue-outstanding.jpg', '2023-07-05', 1, 4, '2025-10-14 19:03:20', '2025-10-14 19:03:20', NULL),
(5, 'Best UI/UX Design Implementation', 'Awarded for exceptional attention to user experience design, accessibility standards, and creating intuitive interfaces that delight users.', 'Design & Development Summit', 'DDS-UIUX-2022', 'https://example.com/credentials/dds-uiux-2022', 'awards/uiux-best.jpg', '2022-12-18', 1, 5, '2025-10-14 19:03:20', '2025-10-14 19:03:20', NULL),
(6, 'Database Architecture Excellence', 'Recognized for designing and implementing efficient database schemas with optimal query performance and data integrity.', 'Database Professionals Association', 'DPA-ARCH-2022', 'https://example.com/credentials/dpa-arch-2022', 'awards/database-excellence.jpg', '2022-08-22', 1, 6, '2025-10-14 19:03:20', '2025-10-14 19:03:20', NULL),
(7, 'Startup Worldcup 2018', '<p>Startup Worldcup 2018 Startup Worldcup 2018 Startup Worldcup 2018</p>', 'Fenox', NULL, NULL, NULL, '2018-10-15', 1, 0, '2025-10-15 01:49:49', '2025-10-15 01:49:49', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `awards_galleries`
--

CREATE TABLE `awards_galleries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `award_id` bigint(20) UNSIGNED NOT NULL,
  `gallery_id` bigint(20) UNSIGNED NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `award_gallery`
--

CREATE TABLE `award_gallery` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `award_id` bigint(20) UNSIGNED NOT NULL,
  `gallery_id` bigint(20) UNSIGNED NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `award_gallery`
--

INSERT INTO `award_gallery` (`id`, `award_id`, `gallery_id`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 1, 13, 0, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(2, 1, 14, 1, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(3, 1, 15, 2, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(4, 2, 16, 0, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(5, 2, 17, 1, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(6, 3, 13, 0, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(7, 3, 14, 1, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(8, 3, 18, 2, '2025-10-14 19:03:20', '2025-10-14 19:03:20');

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `meta_title` varchar(60) DEFAULT NULL,
  `meta_description` varchar(160) DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `og_title` varchar(60) DEFAULT NULL,
  `og_description` text DEFAULT NULL,
  `og_image` varchar(255) DEFAULT NULL,
  `canonical_url` varchar(255) DEFAULT NULL,
  `schema_markup` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`schema_markup`)),
  `index_follow` tinyint(1) NOT NULL DEFAULT 1,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(255) NOT NULL DEFAULT '#3B82F6',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `meta_title`, `meta_description`, `meta_keywords`, `og_title`, `og_description`, `og_image`, `canonical_url`, `schema_markup`, `index_follow`, `slug`, `description`, `color`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(5, 'Web Development', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'web-development', 'Articles about web development, frameworks, and best practices', '#3B82F6', 1, 1, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(6, 'Tutorials', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'tutorials', 'Step-by-step guides and tutorials', '#10B981', 1, 2, '2025-10-14 19:03:20', '2025-10-14 19:03:20');

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `galleries`
--

CREATE TABLE `galleries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `galleries`
--

INSERT INTO `galleries` (`id`, `title`, `description`, `image`, `category`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(13, 'E-commerce Dashboard', 'Modern e-commerce admin dashboard with analytics', 'gallery/ecommerce-dashboard.jpg', 'web', 1, 1, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(14, 'Portfolio Website', 'Minimalist portfolio design with smooth animations', 'gallery/portfolio-website.jpg', 'web', 1, 2, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(15, 'Task Management App', 'Collaborative task management with real-time updates', 'gallery/task-management.jpg', 'web', 1, 3, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(16, 'Fitness Tracker', 'iOS/Android fitness tracking app with workout plans', 'gallery/fitness-tracker.jpg', 'mobile', 1, 1, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(17, 'Food Delivery App', 'Cross-platform food delivery application', 'gallery/food-delivery.jpg', 'mobile', 1, 2, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(18, 'Banking App UI', 'Modern banking app interface design', 'gallery/banking-app-ui.jpg', 'design', 1, 1, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(19, 'Social Media Redesign', 'Social media platform UI/UX redesign concept', 'gallery/social-media-redesign.jpg', 'design', 1, 2, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(20, 'Dashboard Components', 'Reusable dashboard component library', 'gallery/dashboard-components.jpg', 'design', 1, 3, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(21, 'Mountain Landscape', 'Beautiful mountain landscape photography', 'gallery/mountain-landscape.jpg', 'photography', 1, 1, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(22, 'Urban Architecture', 'Modern urban architecture photography', 'gallery/urban-architecture.jpg', 'photography', 1, 2, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(23, 'Tech Startup Logo', 'Minimalist logo design for tech startup', 'gallery/tech-startup-logo.jpg', 'branding', 1, 1, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(24, 'Coffee Shop Branding', 'Complete branding package for coffee shop', 'gallery/coffee-shop-branding.jpg', 'branding', 1, 2, '2025-10-14 19:03:20', '2025-10-14 19:03:20');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `url` varchar(255) NOT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sequence` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `title`, `slug`, `url`, `icon`, `is_active`, `sequence`, `created_at`, `updated_at`) VALUES
(1, 'Home', 'home', '/', 'home', 1, 0, '2025-10-22 13:41:30', '2025-10-22 13:54:14'),
(2, 'About', 'about', '/about', 'user', 1, 1, '2025-10-22 13:42:48', '2025-10-22 14:02:11'),
(3, 'Projects', 'projects', '/projects', 'chart-bar', 1, 3, '2025-10-22 13:47:21', '2025-10-22 14:08:21'),
(4, 'Awards', 'awards', '/awards', 'trophy', 1, 2, '2025-10-22 13:54:08', '2025-10-22 14:02:24'),
(5, 'Services', 'services', '/services', 'briefcase', 1, 4, '2025-10-22 13:54:38', '2025-10-22 14:03:22'),
(6, 'Contact', 'contact', '/contact', 'phone', 1, 5, '2025-10-22 13:55:16', '2025-10-22 14:03:30');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2025_10_02_055429_create_personal_access_tokens_table', 1),
(5, '2025_10_02_060231_create_categories_table', 1),
(6, '2025_10_02_060232_create_projects_table', 1),
(7, '2025_10_02_060233_create_posts_table', 1),
(8, '2025_10_02_060234_create_awards_table', 1),
(9, '2025_10_02_060235_create_services_table', 1),
(10, '2025_10_02_060236_create_galleries_table', 1),
(11, '2025_10_02_060237_create_newsletters_table', 1),
(12, '2025_10_02_060240_create_contacts_table', 1),
(13, '2025_10_02_070000_add_seo_fields_to_posts_table', 2),
(14, '2025_10_02_070001_add_seo_fields_to_projects_table', 2),
(15, '2025_10_02_070002_add_seo_fields_to_categories_table', 2),
(16, '2025_10_03_080000_create_post_translations_table', 3),
(17, '2025_10_03_080001_create_project_translations_table', 3),
(18, '2025_10_10_130456_create_testimonials_table', 4),
(19, '2025_10_10_130510_create_settings_table', 4),
(20, '2025_10_10_130515_create_about_table', 4),
(21, '2025_10_11_070925_add_is_active_and_rename_order_to_services_table', 4),
(22, '2025_10_11_070929_add_is_active_and_rename_order_to_awards_table', 4),
(23, '2025_10_11_070932_add_is_active_and_rename_order_to_galleries_table', 4),
(24, '2025_10_11_070934_add_is_active_and_sort_order_to_posts_table', 4),
(25, '2025_10_11_070937_add_is_active_and_rename_order_to_projects_table', 4),
(26, '2025_10_11_070939_add_is_active_and_rename_order_to_categories_table', 4),
(27, '2025_10_11_070941_create_award_gallery_pivot_table', 4),
(28, '2025_10_14_130026_add_credential_fields_to_awards_table', 4),
(29, '2025_10_16_051922_create_automation_logs_table', 5),
(30, '2025_10_16_120000_create_menu_items_table', 5),
(31, '2025_10_16_120001_create_page_sections_table', 5),
(32, '2025_10_16_120002_add_cta_fields_to_projects_table', 5),
(33, '2025_10_16_150000_add_related_projects_to_projects_table', 5);

-- --------------------------------------------------------

--
-- Table structure for table `newsletters`
--

CREATE TABLE `newsletters` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `is_subscribed` tinyint(1) NOT NULL DEFAULT 1,
  `subscribed_at` timestamp NULL DEFAULT NULL,
  `unsubscribed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `page_sections`
--

CREATE TABLE `page_sections` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `page_type` varchar(50) NOT NULL,
  `section_type` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sequence` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `page_sections`
--

INSERT INTO `page_sections` (`id`, `page_type`, `section_type`, `is_active`, `sequence`, `created_at`, `updated_at`) VALUES
(1, 'homepage', 'hero', 1, 0, '2025-10-22 14:11:22', '2025-10-22 14:11:22'),
(2, 'homepage', 'featured_projects', 1, 1, '2025-10-22 14:11:22', '2025-10-22 14:11:22'),
(3, 'homepage', 'latest_blog', 1, 2, '2025-10-22 14:11:22', '2025-10-22 14:11:22'),
(4, 'homepage', 'testimonials', 0, 3, '2025-10-22 14:11:22', '2025-10-22 14:11:52'),
(5, 'homepage', 'cta', 1, 4, '2025-10-22 14:11:22', '2025-10-22 14:11:22');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `personal_access_tokens`
--

INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 'App\\Models\\User', 1, 'auth-token', 'c9652e428a4e9958e9bb2ec3675ad4d06a1c62cc0b647041fefbb38585cee688', '[\"*\"]', NULL, NULL, '2025-10-14 18:52:51', '2025-10-14 18:52:51'),
(2, 'App\\Models\\User', 9, 'auth-token', 'd0b907ba2eceb0f31777d00b28cbf335be17e6efad555c1e89237a16b26b6082', '[\"*\"]', NULL, NULL, '2025-10-14 19:19:00', '2025-10-14 19:19:00'),
(3, 'App\\Models\\User', 9, 'auth-token', '5846e25c88881162650ca9460511dc80adff36aaf8ebe77f1afaa1475e3aae6c', '[\"*\"]', NULL, NULL, '2025-10-14 20:51:08', '2025-10-14 20:51:08'),
(4, 'App\\Models\\User', 9, 'auth-token', '5d6f02f6ff41de6a2a225551608536f12af168acd5b507c100b29f5295ff6ef2', '[\"*\"]', NULL, NULL, '2025-10-14 20:54:17', '2025-10-14 20:54:17'),
(5, 'App\\Models\\User', 9, 'auth-token', 'fd84ef13eb081ae8ead85208d9771593a129ab1af6775c2581ef12ec8fe36b44', '[\"*\"]', NULL, NULL, '2025-10-14 21:11:31', '2025-10-14 21:11:31'),
(6, 'App\\Models\\User', 9, 'auth-token', 'd1eea2c4f64c0acc4b1890b65014162815cf119882bf1fcd7f0b1fd657819aa1', '[\"*\"]', NULL, NULL, '2025-10-14 21:12:50', '2025-10-14 21:12:50'),
(7, 'App\\Models\\User', 9, 'auth-token', 'a0bfc063cd5d12be1750aa9188e00c8f3b8fb6b0a252610f772cb50c2b3eb189', '[\"*\"]', NULL, NULL, '2025-10-14 21:14:59', '2025-10-14 21:14:59'),
(8, 'App\\Models\\User', 9, 'auth-token', '8c39beed52216144e12442ab97aba3a893f91b9151516c7145bd592fee264567', '[\"*\"]', '2025-10-14 21:20:02', NULL, '2025-10-14 21:16:51', '2025-10-14 21:20:02'),
(9, 'App\\Models\\User', 9, 'auth-token', 'e642cac91617eb6f6126dba7df83c670b4bc6ed2f8c2713895122192604a499d', '[\"*\"]', '2025-10-14 21:20:42', NULL, '2025-10-14 21:20:40', '2025-10-14 21:20:42'),
(10, 'App\\Models\\User', 9, 'auth-token', 'b10868d4923c5da3473f632c5e650581c22e4bb1352c1cf71247c61318bee84a', '[\"*\"]', '2025-10-14 21:21:29', NULL, '2025-10-14 21:21:21', '2025-10-14 21:21:29'),
(11, 'App\\Models\\User', 9, 'auth-token', 'e58860c803b8aca8ecc40fa7d3c445e28a999da6d172130ae44d9183d4407e8f', '[\"*\"]', '2025-10-14 23:43:29', NULL, '2025-10-14 23:41:01', '2025-10-14 23:43:29'),
(12, 'App\\Models\\User', 9, 'auth-token', '684769d55fa088fceb1ffc28488596d15181fe155ba0bc5d8d6687ab1553be68', '[\"*\"]', '2025-10-14 23:44:12', NULL, '2025-10-14 23:43:55', '2025-10-14 23:44:12'),
(13, 'App\\Models\\User', 9, 'auth-token', '0dcd975325c597dd5442d2878e13028be449c1898240a2b141675fcb23fe5a10', '[\"*\"]', '2025-10-14 23:44:34', NULL, '2025-10-14 23:44:32', '2025-10-14 23:44:34'),
(14, 'App\\Models\\User', 9, 'auth-token', '3dd7d6c424dde4cc284487ec6645965b85703e764474c5a6ce4a4c2b456a1f80', '[\"*\"]', '2025-10-14 23:45:52', NULL, '2025-10-14 23:45:30', '2025-10-14 23:45:52'),
(15, 'App\\Models\\User', 9, 'auth-token', 'ba7827326a22379dcc425aab18c42fdc7e8579865fd5986045b47b667bbf3669', '[\"*\"]', '2025-10-14 23:48:00', NULL, '2025-10-14 23:46:25', '2025-10-14 23:48:00'),
(16, 'App\\Models\\User', 9, 'auth-token', '81dfe1af04fb2c3ec15dc78f7529d9ee5b15e23948d0e7902c70dd9c2576bff2', '[\"*\"]', '2025-10-14 23:49:35', NULL, '2025-10-14 23:49:11', '2025-10-14 23:49:35'),
(17, 'App\\Models\\User', 9, 'auth-token', '497cb52bedd2a4bc1e0db959eb7e2e6788850dcc8e31f4ed0c13548e584da796', '[\"*\"]', '2025-10-14 23:56:08', NULL, '2025-10-14 23:52:09', '2025-10-14 23:56:08'),
(18, 'App\\Models\\User', 9, 'auth-token', '448a09aeede0d06abca94acecbdd5ec8adfa3a25265206ca14e883c280d70d99', '[\"*\"]', '2025-10-15 00:39:06', NULL, '2025-10-15 00:20:14', '2025-10-15 00:39:06'),
(19, 'App\\Models\\User', 9, 'auth-token', '2b7d20cdc57a1fe43cf13922f4bb2ebdf4a5378edc1b9d5546014b7f39409b39', '[\"*\"]', '2025-10-15 01:44:38', NULL, '2025-10-15 01:31:01', '2025-10-15 01:44:38'),
(20, 'App\\Models\\User', 9, 'auth-token', '10c1ddb0d4dd2245f0a785bb153d49924254fbca9afe9fa7db31a406b79b53cc', '[\"*\"]', '2025-10-15 01:58:42', NULL, '2025-10-15 01:47:05', '2025-10-15 01:58:42'),
(21, 'App\\Models\\User', 9, 'auth-token', '7d323deb2c4574c390329f5ef7479a9d91aca261411f38f6b7fef5eab9268657', '[\"*\"]', '2025-10-15 02:09:11', NULL, '2025-10-15 01:59:31', '2025-10-15 02:09:11'),
(22, 'App\\Models\\User', 9, 'auth-token', '9096ca75f0b259144a004477a1490fb490131db044ef9d2d995d798d8f86c9a0', '[\"*\"]', '2025-10-15 02:14:21', NULL, '2025-10-15 02:09:29', '2025-10-15 02:14:21'),
(23, 'App\\Models\\User', 9, 'auth-token', 'e2fb604a92700f262f9e2967ff817aa3e4c1a3f56828130bbc48d90ea247c00a', '[\"*\"]', '2025-10-15 02:58:19', NULL, '2025-10-15 02:29:38', '2025-10-15 02:58:19'),
(24, 'App\\Models\\User', 9, 'auth-token', 'be8b4790245fe940ea7fe6f75086f2aa5510703cb3f08b6da99bc5af80b03eed', '[\"*\"]', '2025-10-22 13:33:50', NULL, '2025-10-22 13:24:00', '2025-10-22 13:33:50'),
(25, 'App\\Models\\User', 9, 'auth-token', 'e9744bc0f46ae36fd689254a4df7e7a13d7b9b9dbeb238a81081810b7e74cf5c', '[\"*\"]', '2025-10-22 13:41:29', NULL, '2025-10-22 13:35:28', '2025-10-22 13:41:29'),
(26, 'App\\Models\\User', 9, 'auth-token', 'ca47f7af13eb57a4d00d48ed195e82675bc63b1fac2382ad336327aa3cc2d411', '[\"*\"]', '2025-10-22 13:42:48', NULL, '2025-10-22 13:42:12', '2025-10-22 13:42:48'),
(27, 'App\\Models\\User', 9, 'auth-token', '592799c747fff1b5cd96908f22afc2161bae91c5667d3beb73c049371ac083e2', '[\"*\"]', '2025-10-22 13:53:08', NULL, '2025-10-22 13:47:02', '2025-10-22 13:53:08'),
(28, 'App\\Models\\User', 9, 'auth-token', 'd9d291fe9e74264c3f4f2d056fcdb83ec662ed2896ab6d56deece435b4668e42', '[\"*\"]', '2025-10-22 14:00:35', NULL, '2025-10-22 13:53:41', '2025-10-22 14:00:35'),
(29, 'App\\Models\\User', 9, 'auth-token', '62cd0cd38ac4eaa4dbd608d378583f94b54a337d3c80613b5cc935c769fb6c09', '[\"*\"]', '2025-10-22 14:09:46', NULL, '2025-10-22 14:01:37', '2025-10-22 14:09:46'),
(30, 'App\\Models\\User', 9, 'auth-token', '033cdbb3c807ff13449ca5046620c72a920f2323b77bcb3efd7bc4364eb70719', '[\"*\"]', '2025-10-22 14:36:15', NULL, '2025-10-22 14:11:35', '2025-10-22 14:36:15'),
(31, 'App\\Models\\User', 9, 'auth-token', '49f4de7c74e1ecc79a69173256ec6fda0cf643487640c768814e0ff76bbc885f', '[\"*\"]', '2025-10-22 15:49:01', NULL, '2025-10-22 15:47:15', '2025-10-22 15:49:01'),
(32, 'App\\Models\\User', 9, 'auth-token', '8d56f30254bd1b34b94d12f3bbe70bed96648d5ce8c001fc862d5f5d6ce6a9bf', '[\"*\"]', '2025-10-22 15:49:20', NULL, '2025-10-22 15:49:19', '2025-10-22 15:49:20'),
(33, 'App\\Models\\User', 9, 'auth-token', '382636d2ad699c70e821cc12f2edbdc0eee8909a59b69d5bc4ad247dee0e37ae', '[\"*\"]', '2025-10-22 15:59:31', NULL, '2025-10-22 15:52:46', '2025-10-22 15:59:31'),
(34, 'App\\Models\\User', 9, 'auth-token', '3a187de544d1c06715c7423e5afb3385c85d6e2c6b693514d0285dc618e10a79', '[\"*\"]', '2025-10-22 16:02:51', NULL, '2025-10-22 16:02:22', '2025-10-22 16:02:51'),
(35, 'App\\Models\\User', 9, 'auth-token', 'e6730a865654dc52497fdeabca9ff48792a7c2d757daa43aa328d1fd8937adfe', '[\"*\"]', '2025-10-22 16:08:43', NULL, '2025-10-22 16:06:18', '2025-10-22 16:08:43'),
(36, 'App\\Models\\User', 9, 'auth-token', 'd4c1a24b8aa129c0fc191e902eeb2b8d10125284b1ffcd18265e62a2c24c9de8', '[\"*\"]', '2025-10-22 16:26:05', NULL, '2025-10-22 16:10:04', '2025-10-22 16:26:05'),
(37, 'App\\Models\\User', 9, 'auth-token', 'de54f8f6ca39642a56431c3d32710769c12fc87fcc0c2b34ecc2a97c8f3b60ff', '[\"*\"]', '2025-10-22 16:26:37', NULL, '2025-10-22 16:26:36', '2025-10-22 16:26:37'),
(38, 'App\\Models\\User', 9, 'auth-token', '82a1b0984df7002b2458b022ad41f479b2861e661ec17129cc9b03b807cb8314', '[\"*\"]', '2025-10-22 16:29:14', NULL, '2025-10-22 16:27:15', '2025-10-22 16:29:14'),
(39, 'App\\Models\\User', 9, 'auth-token', 'db50af0f20daa94cad4dcf310fd4e9b02fb9381d96af0c1fd79995d76beb8d5a', '[\"*\"]', '2025-10-22 16:44:54', NULL, '2025-10-22 16:30:05', '2025-10-22 16:44:54'),
(40, 'App\\Models\\User', 9, 'auth-token', '259c0e85bc6ea28e66bae115b0afa46fd09426a1ce7df43c2822078674306985', '[\"*\"]', '2025-10-22 16:48:02', NULL, '2025-10-22 16:45:34', '2025-10-22 16:48:02'),
(41, 'App\\Models\\User', 9, 'auth-token', 'aa7d6366b074c527d01076795f6949dd2d8822122720fed639d2a3acb52e024d', '[\"*\"]', '2025-10-22 16:48:35', NULL, '2025-10-22 16:48:28', '2025-10-22 16:48:35'),
(42, 'App\\Models\\User', 9, 'auth-token', '5ebba9ceca779f4871f7655fe001238a7d3c35813b1c47e98b8ad44df31677c8', '[\"*\"]', '2025-10-22 16:54:31', NULL, '2025-10-22 16:54:30', '2025-10-22 16:54:31'),
(43, 'App\\Models\\User', 9, 'auth-token', '072a58ac26f363589500d707c26d6bff8dd4c3e6368681b8508576f9a40bf82b', '[\"*\"]', '2025-10-22 16:55:10', NULL, '2025-10-22 16:55:09', '2025-10-22 16:55:10'),
(44, 'App\\Models\\User', 9, 'auth-token', 'aa2dbe5d5a40ed376391344e00dddb8ff00e16b8a454c1f0a6a8d551f37b7804', '[\"*\"]', '2025-10-22 16:56:04', NULL, '2025-10-22 16:56:03', '2025-10-22 16:56:04');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `meta_title` varchar(60) DEFAULT NULL,
  `meta_description` varchar(160) DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `og_title` varchar(60) DEFAULT NULL,
  `og_description` text DEFAULT NULL,
  `og_image` varchar(255) DEFAULT NULL,
  `canonical_url` varchar(255) DEFAULT NULL,
  `schema_markup` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`schema_markup`)),
  `ai_summary` text DEFAULT NULL,
  `faq_schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`faq_schema`)),
  `seo_score` int(11) NOT NULL DEFAULT 0,
  `index_follow` tinyint(1) NOT NULL DEFAULT 1,
  `slug` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext NOT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `is_premium` tinyint(1) NOT NULL DEFAULT 0,
  `published` tinyint(1) NOT NULL DEFAULT 1,
  `published_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `views` int(11) NOT NULL DEFAULT 0,
  `reading_time` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `language` varchar(5) DEFAULT 'en'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `category_id`, `title`, `meta_title`, `meta_description`, `meta_keywords`, `og_title`, `og_description`, `og_image`, `canonical_url`, `schema_markup`, `ai_summary`, `faq_schema`, `seo_score`, `index_follow`, `slug`, `excerpt`, `content`, `featured_image`, `tags`, `is_premium`, `published`, `published_at`, `is_active`, `sort_order`, `views`, `reading_time`, `created_at`, `updated_at`, `deleted_at`, `language`) VALUES
(5, 5, 'Getting Started with Vue 3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 'getting-started-vue3', 'Learn the basics of Vue 3 and build your first application', '<p>Vue 3 is the latest version of the progressive JavaScript framework. In this comprehensive guide, we will explore the new features including the Composition API, improved performance, and TypeScript support.</p><p>We will build a complete application from scratch, covering components, state management, routing, and deployment strategies. By the end of this tutorial, you will have a solid understanding of Vue 3 fundamentals.</p>', 'posts/vue3-tutorial.jpg', '[\"vue\",\"javascript\",\"frontend\",\"tutorial\"]', 0, 1, '2025-10-09 19:03:20', 1, 0, 152, 1, '2025-10-14 19:03:20', '2025-10-15 00:09:42', NULL, 'en'),
(6, 5, 'Laravel 12 New Features', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 'laravel-12-new-features', 'Explore the exciting new features in Laravel 12', '<p>Laravel 12 brings significant improvements to the framework. We will dive deep into the new features including enhanced database query builder, improved testing utilities, and better performance.</p><p>This article covers practical examples and migration guides to help you upgrade your existing Laravel applications to version 12.</p>', 'posts/laravel-12.jpg', '[\"laravel\",\"php\",\"backend\",\"framework\"]', 0, 1, '2025-10-12 19:03:20', 1, 0, 90, 1, '2025-10-14 19:03:20', '2025-10-14 19:04:33', NULL, 'en');

-- --------------------------------------------------------

--
-- Table structure for table `post_translations`
--

CREATE TABLE `post_translations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `post_id` bigint(20) UNSIGNED NOT NULL,
  `language` varchar(5) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext NOT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `og_title` varchar(255) DEFAULT NULL,
  `og_description` text DEFAULT NULL,
  `canonical_url` varchar(255) DEFAULT NULL,
  `ai_summary` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `post_translations`
--

INSERT INTO `post_translations` (`id`, `post_id`, `language`, `title`, `slug`, `excerpt`, `content`, `meta_title`, `meta_description`, `og_title`, `og_description`, `canonical_url`, `ai_summary`, `created_at`, `updated_at`) VALUES
(9, 5, 'en', 'Getting Started with Vue 3', 'getting-started-vue3', 'Learn the basics of Vue 3 and build your first application', '<p>Vue 3 is the latest version of the progressive JavaScript framework. In this comprehensive guide, we will explore the new features including the Composition API, improved performance, and TypeScript support.</p><p>We will build a complete application from scratch, covering components, state management, routing, and deployment strategies. By the end of this tutorial, you will have a solid understanding of Vue 3 fundamentals.</p>', 'Getting Started with Vue 3 - Complete Guide', 'Learn Vue 3 from scratch with this comprehensive tutorial covering Composition API, components, and more.', NULL, NULL, NULL, NULL, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(10, 5, 'id', 'Memulai dengan Vue 3', 'memulai-dengan-vue3', 'Pelajari dasar-dasar Vue 3 dan bangun aplikasi pertama Anda', '<p>Vue 3 adalah versi terbaru dari framework JavaScript progresif. Dalam panduan komprehensif ini, kita akan menjelajahi fitur-fitur baru termasuk Composition API, peningkatan performa, dan dukungan TypeScript.</p><p>Kita akan membangun aplikasi lengkap dari awal, mencakup komponen, manajemen state, routing, dan strategi deployment. Di akhir tutorial ini, Anda akan memiliki pemahaman yang solid tentang fundamental Vue 3.</p>', 'Memulai dengan Vue 3 - Panduan Lengkap', 'Pelajari Vue 3 dari awal dengan tutorial komprehensif ini yang mencakup Composition API, komponen, dan lainnya.', NULL, NULL, NULL, NULL, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(11, 6, 'en', 'Laravel 12 New Features', 'laravel-12-new-features', 'Explore the exciting new features in Laravel 12', '<p>Laravel 12 brings significant improvements to the framework. We will dive deep into the new features including enhanced database query builder, improved testing utilities, and better performance.</p><p>This article covers practical examples and migration guides to help you upgrade your existing Laravel applications to version 12.</p>', 'Laravel 12 New Features - Complete Overview', 'Discover the new features in Laravel 12 and learn how to upgrade your applications.', NULL, NULL, NULL, NULL, '2025-10-14 19:03:20', '2025-10-14 19:03:20'),
(12, 6, 'id', 'Fitur Baru Laravel 12', 'fitur-baru-laravel-12', 'Jelajahi fitur-fitur baru yang menarik di Laravel 12', '<p>Laravel 12 membawa peningkatan signifikan ke framework. Kita akan mendalami fitur-fitur baru termasuk query builder database yang ditingkatkan, utilitas testing yang lebih baik, dan performa yang lebih bagus.</p><p>Artikel ini mencakup contoh praktis dan panduan migrasi untuk membantu Anda mengupgrade aplikasi Laravel yang ada ke versi 12.</p>', 'Fitur Baru Laravel 12 - Overview Lengkap', 'Temukan fitur-fitur baru di Laravel 12 dan pelajari cara mengupgrade aplikasi Anda.', NULL, NULL, NULL, NULL, '2025-10-14 19:03:20', '2025-10-14 19:03:20');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` longtext NOT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `project_url` varchar(255) DEFAULT NULL,
  `github_url` varchar(255) DEFAULT NULL,
  `related_project_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`related_project_ids`)),
  `cta_title` varchar(255) DEFAULT NULL,
  `cta_description` text DEFAULT NULL,
  `cta_button_text` varchar(100) DEFAULT NULL,
  `cta_phone_number` varchar(20) DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `technologies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`technologies`)),
  `status` enum('planning','in_progress','completed') DEFAULT 'planning',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `meta_title` varchar(60) DEFAULT NULL,
  `meta_description` varchar(160) DEFAULT NULL,
  `focus_keyword` varchar(100) DEFAULT NULL,
  `canonical_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `title`, `slug`, `description`, `client_name`, `project_url`, `github_url`, `related_project_ids`, `cta_title`, `cta_description`, `cta_button_text`, `cta_phone_number`, `featured_image`, `is_featured`, `technologies`, `status`, `start_date`, `end_date`, `meta_title`, `meta_description`, `focus_keyword`, `canonical_url`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Mysatnusa Platform 2', 'mysatnusa-platform-2', '<p>Mysatnusa Platform Mysatnusa Platform Mysatnusa Platform</p>', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '[\"Django\",\"PostgreSQL\",\"VUE JS\"]', 'planning', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-15 00:38:04', '2025-10-15 00:39:03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `projects_backup`
--

CREATE TABLE `projects_backup` (
  `id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta_title` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_keywords` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `og_title` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `og_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `og_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `canonical_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schema_markup` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`schema_markup`)),
  `ai_summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tech_stack_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tech_stack_details`)),
  `seo_score` int(11) NOT NULL DEFAULT 0,
  `index_follow` tinyint(1) NOT NULL DEFAULT 1,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('planning','in_progress','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planning',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `featured_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`)),
  `category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `technologies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`technologies`)),
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `client_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `github_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completed_at` date DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `published` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `language` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects_backup`
--

INSERT INTO `projects_backup` (`id`, `title`, `meta_title`, `meta_description`, `meta_keywords`, `og_title`, `og_description`, `og_image`, `canonical_url`, `schema_markup`, `ai_summary`, `tech_stack_details`, `seo_score`, `index_follow`, `slug`, `description`, `status`, `start_date`, `end_date`, `content`, `featured_image`, `images`, `category`, `technologies`, `tags`, `client_name`, `project_url`, `github_url`, `completed_at`, `is_featured`, `published`, `is_active`, `sort_order`, `created_at`, `updated_at`, `deleted_at`, `language`) VALUES
(7, 'E-commerce Platform', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 'ecommerce-platform', 'A modern, scalable e-commerce platform built with Laravel and React', 'planning', NULL, NULL, 'A comprehensive e-commerce solution featuring product management, shopping cart, payment integration, and admin dashboard. Built with Laravel 12 backend and React 18 frontend.', 'projects/ecommerce.jpg', '[\"projects\\/ecommerce\\/1.jpg\",\"projects\\/ecommerce\\/2.jpg\",\"projects\\/ecommerce\\/3.jpg\"]', 'web', '[\"Laravel\",\"React\",\"MySQL\",\"Redis\",\"Stripe\"]', NULL, 'ABC Corporation', 'https://example.com', NULL, '2024-06-30', 1, 1, 1, 1, '2025-10-14 19:03:20', '2025-10-14 21:17:57', '2025-10-14 21:17:57', 'en'),
(8, 'Mysatnusa Platform', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 'mysatnusa-platform', '<p>Mysatnusa Platform Mysatnusa Platform Mysatnusa Platform Mysatnusa Platform</p>', 'planning', NULL, NULL, 'A powerful task management application with real-time collaboration, project tracking, and team communication features.', 'projects/task-app.jpg', '[\"projects\\/task-app\\/1.jpg\",\"projects\\/task-app\\/2.jpg\"]', 'web', '[\"Vue.js\",\"WebSockets\",\"PostgreSQL\",\"Django\"]', NULL, 'Internal Project', 'https://tasks.example.com', NULL, NULL, 0, 1, 1, 2, '2025-10-14 19:03:20', '2025-10-14 23:56:06', NULL, 'en');

-- --------------------------------------------------------

--
-- Table structure for table `project_translations`
--

CREATE TABLE `project_translations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `project_id` bigint(20) UNSIGNED NOT NULL,
  `language` varchar(2) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` longtext NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `content` longtext DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` longtext DEFAULT NULL,
  `group` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'text',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `group`, `type`, `created_at`, `updated_at`) VALUES
(1, 'name', 'Ali Sadikin', 'about', 'text', '2025-10-22 16:10:45', '2025-10-22 16:10:45'),
(2, 'title', 'AI Generalist Expert', 'about', 'text', '2025-10-22 16:10:45', '2025-10-22 16:10:45'),
(3, 'bio', 'Building AI automation untuk democratize viral content creation di Asia. Ex-community builder Seefluencer. Percaya setiap creator berhak punya tools untuk succeed. Currently: menghubungkan 500M+ aspiring creators dengan viral intelligence platform. Let\'s engineer virality, not chase it.', 'about', 'text', '2025-10-22 16:10:45', '2025-10-22 16:10:45'),
(4, 'profile_photo', '/uploads/about/1761175821_creator_face.png', 'about', 'image', '2025-10-22 16:10:45', '2025-10-22 16:30:21'),
(7, 'social_links', '[{\"platform\":\"Youtube\",\"url\":\"https:\\/\\/www.youtube.com\\/@alisadikinma\",\"icon\":\"\"},{\"platform\":\"Tik-Tok\",\"url\":\"https:\\/\\/www.tiktok.com\\/@alisadikinma\",\"icon\":\"\"},{\"platform\":\"Instagram\",\"url\":\"https:\\/\\/www.instagram.com\\/alisadikinma\\/\",\"icon\":\"\"}]', 'about', 'json', '2025-10-22 16:23:08', '2025-10-22 16:26:05');

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `job_title` varchar(255) DEFAULT NULL,
  `testimonial_text` longtext NOT NULL,
  `client_photo` varchar(255) DEFAULT NULL,
  `star_rating` tinyint(4) NOT NULL DEFAULT 5,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
(9, 'Ali Sadikin', 'admin@alisadikinma.com', '2025-10-14 19:03:19', '$2y$12$JZvsn3X/0NEeSCe8laXa6ekBQcRCg23qgvDNlchDzNgtPQsQPRpZ2', NULL, '2025-10-14 19:03:19', '2025-10-14 19:03:19');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `about`
--
ALTER TABLE `about`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `automation_logs`
--
ALTER TABLE `automation_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `automation_logs_user_id_created_at_index` (`user_id`,`created_at`),
  ADD KEY `automation_logs_action_created_at_index` (`action`,`created_at`);

--
-- Indexes for table `awards`
--
ALTER TABLE `awards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `awards_is_active_index` (`is_active`),
  ADD KEY `awards_sort_order_index` (`sort_order`);

--
-- Indexes for table `awards_galleries`
--
ALTER TABLE `awards_galleries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_award_gallery` (`award_id`,`gallery_id`),
  ADD KEY `idx_award_id` (`award_id`),
  ADD KEY `idx_gallery_id` (`gallery_id`);

--
-- Indexes for table `award_gallery`
--
ALTER TABLE `award_gallery`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `award_gallery_award_id_gallery_id_unique` (`award_id`,`gallery_id`),
  ADD KEY `award_gallery_award_id_index` (`award_id`),
  ADD KEY `award_gallery_gallery_id_index` (`gallery_id`),
  ADD KEY `award_gallery_sort_order_index` (`sort_order`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `categories_slug_unique` (`slug`),
  ADD KEY `categories_meta_title_index` (`meta_title`),
  ADD KEY `categories_index_follow_index` (`index_follow`),
  ADD KEY `categories_is_active_index` (`is_active`),
  ADD KEY `categories_sort_order_index` (`sort_order`);

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `galleries`
--
ALTER TABLE `galleries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `galleries_is_active_index` (`is_active`),
  ADD KEY `galleries_sort_order_index` (`sort_order`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `menu_items_slug_unique` (`slug`),
  ADD KEY `menu_items_is_active_index` (`is_active`),
  ADD KEY `menu_items_sequence_index` (`sequence`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `newsletters`
--
ALTER TABLE `newsletters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `newsletters_email_unique` (`email`);

--
-- Indexes for table `page_sections`
--
ALTER TABLE `page_sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_sections_page_type_section_type_unique` (`page_type`,`section_type`),
  ADD KEY `page_sections_page_type_index` (`page_type`),
  ADD KEY `page_sections_is_active_index` (`is_active`),
  ADD KEY `page_sections_sequence_index` (`sequence`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `posts_slug_unique` (`slug`),
  ADD KEY `posts_category_id_foreign` (`category_id`),
  ADD KEY `posts_meta_title_index` (`meta_title`),
  ADD KEY `posts_seo_score_index` (`seo_score`),
  ADD KEY `posts_index_follow_index` (`index_follow`),
  ADD KEY `posts_is_active_index` (`is_active`),
  ADD KEY `posts_sort_order_index` (`sort_order`);

--
-- Indexes for table `post_translations`
--
ALTER TABLE `post_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `post_translations_post_id_language_unique` (`post_id`,`language`),
  ADD KEY `post_translations_language_index` (`language`),
  ADD KEY `post_translations_slug_index` (`slug`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_is_featured` (`is_featured`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_deleted_at` (`deleted_at`);

--
-- Indexes for table `project_translations`
--
ALTER TABLE `project_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_project_language` (`project_id`,`language`),
  ADD KEY `idx_language` (`language`),
  ADD KEY `idx_slug` (`slug`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `services_slug_unique` (`slug`),
  ADD KEY `services_is_active_index` (`is_active`),
  ADD KEY `services_sort_order_index` (`sort_order`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `settings_key_unique` (`key`),
  ADD KEY `settings_group_index` (`group`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `testimonials_is_active_index` (`is_active`),
  ADD KEY `testimonials_sort_order_index` (`sort_order`),
  ADD KEY `testimonials_star_rating_index` (`star_rating`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `about`
--
ALTER TABLE `about`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `automation_logs`
--
ALTER TABLE `automation_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `awards`
--
ALTER TABLE `awards`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `awards_galleries`
--
ALTER TABLE `awards_galleries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `award_gallery`
--
ALTER TABLE `award_gallery`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `contacts`
--
ALTER TABLE `contacts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `galleries`
--
ALTER TABLE `galleries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `newsletters`
--
ALTER TABLE `newsletters`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `page_sections`
--
ALTER TABLE `page_sections`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `post_translations`
--
ALTER TABLE `post_translations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `project_translations`
--
ALTER TABLE `project_translations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `automation_logs`
--
ALTER TABLE `automation_logs`
  ADD CONSTRAINT `automation_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `awards_galleries`
--
ALTER TABLE `awards_galleries`
  ADD CONSTRAINT `fk_award_id` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_gallery_id` FOREIGN KEY (`gallery_id`) REFERENCES `galleries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `award_gallery`
--
ALTER TABLE `award_gallery`
  ADD CONSTRAINT `award_gallery_award_id_foreign` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `award_gallery_gallery_id_foreign` FOREIGN KEY (`gallery_id`) REFERENCES `galleries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `post_translations`
--
ALTER TABLE `post_translations`
  ADD CONSTRAINT `post_translations_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_translations`
--
ALTER TABLE `project_translations`
  ADD CONSTRAINT `fk_project_translations_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
