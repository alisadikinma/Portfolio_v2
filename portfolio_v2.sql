-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 30, 2025 at 11:03 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `received_at` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `awards`
--

INSERT INTO `awards` (`id`, `title`, `description`, `organization`, `credential_id`, `credential_url`, `image`, `received_at`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(7, '1st Place Winner Nextdev Startup Competition', '<p>Achieved first place in prestigious Nextdev Startup Competition, demonstrating innovative digital solutions and entrepreneurial excellence.</p>', 'TELKOMSEL', 'AWARD-1761800275656-D53KZV', 'https://credentials.portfolio.com/verify/AWARD-1761800275656-D53KZV', '/uploads/awards/1761800305_award-nextdev.jpg', '2018', 1, 0, '2025-10-29 21:58:25', '2025-10-29 21:58:25'),
(8, '1st Place Winner Wild Card Fenox Startup World Cup Competition', '<p>Won first place in Wild Card category of Fenox Startup World Cup Competition, showcasing global-level innovation and business excellence.</p>', 'FENOX - BEKRAF', 'AWARD-1761804295613-J5Q14Z', 'https://credentials.portfolio.com/verify/AWARD-1761804295613-J5Q14Z', '/uploads/awards/1761804321_award-fenox.jpg', '2017', 1, 2, '2025-10-29 23:05:21', '2025-10-29 23:05:21');

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
(1, 'Web Development', 'Web Development Articles', 'Read the latest articles about web development, programming languages, frameworks, and best practices.', NULL, NULL, NULL, NULL, NULL, NULL, 1, 'web-development', 'Articles about web development, programming, and coding', '#3b82f6', 1, 0, '2025-10-29 16:26:35', '2025-10-29 16:26:35'),
(2, 'Design', 'Design Articles', 'Explore articles about UI/UX design, graphic design, and creative processes.', NULL, NULL, NULL, NULL, NULL, NULL, 1, 'design', 'UI/UX design, graphic design, and creative inspiration', '#ec4899', 1, 0, '2025-10-29 16:26:35', '2025-10-29 16:26:35'),
(3, 'Technology', 'Technology News', 'Stay updated with the latest technology news, trends, and innovations.', NULL, NULL, NULL, NULL, NULL, NULL, 1, 'technology', 'Latest tech news, trends, and innovations', '#10b981', 1, 0, '2025-10-29 16:26:35', '2025-10-29 16:26:35'),
(4, 'Tutorial', 'Tutorials and Guides', 'Learn with our comprehensive tutorials and step-by-step guides.', NULL, NULL, NULL, NULL, NULL, NULL, 1, 'tutorial', 'Step-by-step guides and tutorials', '#f59e0b', 1, 0, '2025-10-29 16:26:35', '2025-10-29 16:26:35'),
(5, 'Career', 'Career Development', 'Get career advice, tips, and insights for professional growth.', NULL, NULL, NULL, NULL, NULL, NULL, 1, 'career', 'Career advice, tips, and professional development', '#8b5cf6', 1, 0, '2025-10-29 16:26:35', '2025-10-29 16:26:35'),
(6, 'Personal', 'Personal Blog', 'Read personal thoughts, experiences, and stories.', NULL, NULL, NULL, NULL, NULL, NULL, 1, 'personal', 'Personal thoughts, experiences, and stories', '#ef4444', 1, 0, '2025-10-29 16:26:35', '2025-10-29 16:26:35'),
(7, 'Tutorials', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'tutorials', 'Step-by-step guides and tutorials', '#10B981', 1, 2, '2025-10-29 16:26:35', '2025-10-29 16:26:35');

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
  `company` varchar(255) DEFAULT NULL,
  `period` varchar(100) DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `award_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `galleries`
--

INSERT INTO `galleries` (`id`, `title`, `description`, `company`, `period`, `thumbnail`, `award_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(11, '1st Place Winner Nextdev Startup Competition', 'Recognized as Telkomsel’s flagship startup program, NextDev empowers young innovators to create impactful digital solutions. Winning 1st place highlights innovation, vision, and execution in driving Indonesia’s digital transformation and shaping future global leaders.', NULL, NULL, '/storage/gallery/thumbnails/1761792762_thumb_1st-place-winner-nextdev-startup-competition.png', 7, 1, 0, '2025-10-29 19:52:42', '2025-10-29 21:58:28'),
(12, '1st Wild Card Winner – Startup World Cup', 'Organized by Fenox Venture Capital in collaboration with Indonesia’s Creative Economy Agency (BEKRAF), the Startup World Cup is a prestigious global competition connecting startups with top investors and tech leaders. Winning the Wild Card Round in Indonesia highlighted innovation, resilience, and global potential—granting the opportunity to join the regional finals and gain exposure to Silicon Valley’s ecosystem.', NULL, NULL, '/storage/gallery/thumbnails/1761793546_thumb_1st-wild-card-winner-startup-world-cup.png', 8, 1, 0, '2025-10-29 20:05:46', '2025-10-29 23:05:23');

-- --------------------------------------------------------

--
-- Table structure for table `gallery_items`
--

CREATE TABLE `gallery_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `gallery_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('image','video') NOT NULL DEFAULT 'image',
  `file_path` varchar(500) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sequence` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gallery_items`
--

INSERT INTO `gallery_items` (`id`, `gallery_id`, `type`, `file_path`, `title`, `description`, `sequence`, `created_at`, `updated_at`) VALUES
(28, 11, 'image', 'gallery/items/1761792762_item_11_0.jpg', '1st Place Winner Nextdev Startup Competition - Image 1', NULL, 0, '2025-10-29 19:52:42', '2025-10-29 19:52:42'),
(29, 11, 'image', 'gallery/items/1761792762_item_11_1.jpg', '1st Place Winner Nextdev Startup Competition - Image 2', NULL, 1, '2025-10-29 19:52:42', '2025-10-29 19:52:42'),
(30, 11, 'image', 'gallery/items/1761792762_item_11_2.jpg', '1st Place Winner Nextdev Startup Competition - Image 3', NULL, 2, '2025-10-29 19:52:42', '2025-10-29 19:52:42'),
(31, 11, 'image', 'gallery/items/1761792762_item_11_3.jpg', '1st Place Winner Nextdev Startup Competition - Image 4', NULL, 3, '2025-10-29 19:52:42', '2025-10-29 19:52:42'),
(32, 11, 'image', 'gallery/items/1761792762_item_11_4.jpg', '1st Place Winner Nextdev Startup Competition - Image 5', NULL, 4, '2025-10-29 19:52:42', '2025-10-29 19:52:42'),
(33, 12, 'image', 'gallery/items/1761793546_item_12_0.jpg', '1st Wild Card Winner – Startup World Cup - Image 1', NULL, 0, '2025-10-29 20:05:46', '2025-10-29 20:05:46'),
(34, 12, 'image', 'gallery/items/1761793546_item_12_1.jpg', '1st Wild Card Winner – Startup World Cup - Image 2', NULL, 1, '2025-10-29 20:05:46', '2025-10-29 20:05:46'),
(35, 12, 'image', 'gallery/items/1761793546_item_12_2.jpg', '1st Wild Card Winner – Startup World Cup - Image 3', NULL, 2, '2025-10-29 20:05:46', '2025-10-29 20:05:46'),
(36, 12, 'image', 'gallery/items/1761793546_item_12_3.jpg', '1st Wild Card Winner – Startup World Cup - Image 4', NULL, 3, '2025-10-29 20:05:46', '2025-10-29 20:05:46');

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
(1, 'Home', 'home', '/', 'home', 1, 0, '2025-10-29 16:26:35', '2025-10-29 16:26:35'),
(2, 'About', 'about', '/about', 'information-circle', 1, 1, '2025-10-29 16:26:35', '2025-10-29 16:31:02'),
(3, 'Projects', 'projects', '/projects', 'briefcase', 1, 2, '2025-10-29 16:26:35', '2025-10-29 16:26:35'),
(4, 'Awards', 'awards', '/awards', 'trophy', 1, 3, '2025-10-29 16:26:35', '2025-10-29 16:31:14'),
(5, 'Blog', 'blog', '/blog', 'newspaper', 1, 4, '2025-10-29 16:26:35', '2025-10-29 16:31:37'),
(6, 'Gallery', 'gallery', '/gallery', 'photograph', 1, 5, '2025-10-29 16:26:35', '2025-10-29 16:31:47'),
(7, 'Contact', 'contact', '/contact', 'mail', 1, 6, '2025-10-29 16:26:35', '2025-10-29 16:26:35');

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
(13, '2025_10_02_070000_add_seo_fields_to_posts_table', 1),
(14, '2025_10_02_070001_add_seo_fields_to_projects_table', 1),
(15, '2025_10_02_070002_add_seo_fields_to_categories_table', 1),
(16, '2025_10_03_080000_create_post_translations_table', 1),
(17, '2025_10_03_080001_create_project_translations_table', 1),
(18, '2025_10_10_130456_create_testimonials_table', 1),
(19, '2025_10_10_130510_create_settings_table', 1),
(20, '2025_10_10_130515_create_about_table', 1),
(21, '2025_10_11_070925_add_is_active_and_rename_order_to_services_table', 1),
(22, '2025_10_11_070929_add_is_active_and_rename_order_to_awards_table', 1),
(23, '2025_10_11_070932_add_is_active_and_rename_order_to_galleries_table', 1),
(24, '2025_10_11_070934_add_is_active_and_sort_order_to_posts_table', 1),
(25, '2025_10_11_070937_add_is_active_and_rename_order_to_projects_table', 1),
(26, '2025_10_11_070939_add_is_active_and_rename_order_to_categories_table', 1),
(27, '2025_10_11_070941_create_award_gallery_pivot_table', 1),
(28, '2025_10_14_130026_add_credential_fields_to_awards_table', 1),
(29, '2025_10_16_051922_create_automation_logs_table', 1),
(30, '2025_10_16_120000_create_menu_items_table', 1),
(31, '2025_10_16_120001_create_page_sections_table', 1),
(32, '2025_10_16_120002_add_cta_fields_to_projects_table', 1),
(33, '2025_10_16_150000_add_related_projects_to_projects_table', 1),
(34, '2025_10_25_083505_restructure_galleries_system', 1),
(35, '2025_10_30_000001_change_received_at_to_string_in_awards_table', 2);

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
(1, 'homepage', 'hero', 1, 0, '2025-10-29 16:26:36', '2025-10-29 16:26:36'),
(2, 'homepage', 'featured_projects', 1, 1, '2025-10-29 16:26:36', '2025-10-29 16:26:36'),
(3, 'homepage', 'latest_blog', 1, 2, '2025-10-29 16:26:36', '2025-10-29 16:26:36'),
(4, 'homepage', 'testimonials', 0, 3, '2025-10-29 16:26:36', '2025-10-29 16:32:51'),
(5, 'homepage', 'cta', 1, 4, '2025-10-29 16:26:36', '2025-10-29 16:26:36'),
(6, 'about', 'featured_projects', 0, 0, '2025-10-29 16:26:36', '2025-10-29 16:26:36'),
(7, 'about', 'latest_blog', 0, 1, '2025-10-29 16:26:36', '2025-10-29 16:26:36'),
(8, 'about', 'cta', 0, 2, '2025-10-29 16:26:36', '2025-10-29 16:26:36'),
(9, 'projects', 'latest_blog', 0, 0, '2025-10-29 16:26:36', '2025-10-29 16:26:36'),
(10, 'projects', 'cta', 0, 1, '2025-10-29 16:26:36', '2025-10-29 16:26:36'),
(11, 'blog', 'featured_projects', 0, 0, '2025-10-29 16:26:36', '2025-10-29 16:26:36'),
(12, 'blog', 'cta', 0, 1, '2025-10-29 16:26:36', '2025-10-29 16:26:36');

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
(1, 'App\\Models\\User', 1, 'auth-token', '37b21c234234111d3099a2df4a40f3db273dee03218cf523cfa39775c9696f21', '[\"*\"]', '2025-10-29 16:38:14', NULL, '2025-10-29 16:26:49', '2025-10-29 16:38:14'),
(2, 'App\\Models\\User', 1, 'auth-token', '1813b085d990bdc4eda03e93e67edc691b583be926d87ff084ef0da7ce121b45', '[\"*\"]', NULL, NULL, '2025-10-29 16:39:01', '2025-10-29 16:39:01'),
(3, 'App\\Models\\User', 1, 'auth-token', 'b2c00b7074a62bfad16f7ceb2189bee32516f3b60feb2ab0192c5279676f6410', '[\"*\"]', '2025-10-29 16:43:38', NULL, '2025-10-29 16:41:43', '2025-10-29 16:43:38'),
(4, 'App\\Models\\User', 1, 'auth-token', '05a1ec8e4d434139c1c9cb1e52aa7b80a0fb4a2222fc8638c24ddfbc2e712cca', '[\"*\"]', '2025-10-29 16:44:18', NULL, '2025-10-29 16:44:17', '2025-10-29 16:44:18'),
(5, 'App\\Models\\User', 1, 'auth-token', 'bc9a2263cb08051da92d745b6f9e32b99243d2fea8408a0659867c766bc5a5a5', '[\"*\"]', '2025-10-29 16:46:38', NULL, '2025-10-29 16:45:30', '2025-10-29 16:46:38'),
(6, 'App\\Models\\User', 1, 'auth-token', '003058269f713c258833e45d9b3e6be25a6b1c338def0f87086f9778f9b9eb75', '[\"*\"]', '2025-10-29 17:21:07', NULL, '2025-10-29 17:21:01', '2025-10-29 17:21:07'),
(7, 'App\\Models\\User', 1, 'auth-token', '520cae5ce819be685d7429e9dc145ae9787097345cfdfd6359f19318310a2671', '[\"*\"]', '2025-10-29 17:24:58', NULL, '2025-10-29 17:24:45', '2025-10-29 17:24:58'),
(8, 'App\\Models\\User', 1, 'auth-token', '824aa87b4cff87457773a0fba3a36947d7db17faf511ef4f37dafd3dfdba33dc', '[\"*\"]', '2025-10-29 17:32:29', NULL, '2025-10-29 17:29:14', '2025-10-29 17:32:29'),
(9, 'App\\Models\\User', 1, 'auth-token', 'ed3160ae30e8597f290eda01246d4fcc2219738aa8b7c337a1894c06331899c6', '[\"*\"]', NULL, NULL, '2025-10-29 17:40:46', '2025-10-29 17:40:46'),
(10, 'App\\Models\\User', 1, 'auth-token', '2093a5f7bf982929f90326825ac9ceb1e4567fd1d09d99542accbac670fcf83a', '[\"*\"]', '2025-10-29 18:52:07', NULL, '2025-10-29 18:51:47', '2025-10-29 18:52:07'),
(11, 'App\\Models\\User', 1, 'auth-token', 'ca5eaed1ea6c7f16d63e5e24cd19934cdb91279eec2e99a9563cc7c70c915188', '[\"*\"]', '2025-10-29 18:58:18', NULL, '2025-10-29 18:58:15', '2025-10-29 18:58:18'),
(12, 'App\\Models\\User', 3, 'auth-token', 'd0df102c7847a440320ef5473722555932850062df80ee8e5d6c6766f6143526', '[\"*\"]', NULL, NULL, '2025-10-29 19:34:19', '2025-10-29 19:34:19'),
(13, 'App\\Models\\User', 3, 'auth-token', '01e63c71b290d26b022c5b69ea8bfcdd5514eba8ad48d7960c4b9144f90adba1', '[\"*\"]', '2025-10-29 19:35:20', NULL, '2025-10-29 19:35:18', '2025-10-29 19:35:20'),
(14, 'App\\Models\\User', 3, 'auth-token', 'dfb0be9f7a1e7711e8062698a2f759806eb7cf6314b8e66e3bc9a936441518ec', '[\"*\"]', '2025-10-29 19:38:00', NULL, '2025-10-29 19:36:32', '2025-10-29 19:38:00'),
(15, 'App\\Models\\User', 3, 'auth-token', '935fec5c904d3d45e3511514b101b632c2ebfc19be1adf8f17869c33a5561042', '[\"*\"]', NULL, NULL, '2025-10-29 19:43:11', '2025-10-29 19:43:11'),
(16, 'App\\Models\\User', 3, 'auth-token', '9741f8ada8163bc11ae2e6ade6568019e07cfe530cd65b3839ca2b9d9fd748cd', '[\"*\"]', '2025-10-29 21:12:11', NULL, '2025-10-29 19:49:45', '2025-10-29 21:12:11'),
(17, 'App\\Models\\User', 3, 'auth-token', 'ae7906eb2b001bc2749c024d285473260a671c17eaeb73abc7b2c6c29a751a1a', '[\"*\"]', '2025-10-29 21:13:04', NULL, '2025-10-29 21:12:54', '2025-10-29 21:13:04'),
(19, 'App\\Models\\User', 3, 'auth-token', '09e93c7b86b8717298a0f19db4464c840c4720d0f790143780f3c43260097e4b', '[\"*\"]', '2025-10-29 21:39:40', NULL, '2025-10-29 21:33:14', '2025-10-29 21:39:40'),
(20, 'App\\Models\\User', 3, 'auth-token', '29f7cbd1a66a3d0b03de28edf361d91f056229734bc82e16a85df312ab014559', '[\"*\"]', '2025-10-29 21:51:58', NULL, '2025-10-29 21:43:04', '2025-10-29 21:51:58'),
(21, 'App\\Models\\User', 3, 'auth-token', 'e3850d27a5c97f77a206eb54dc768fbd7ce683a254bd29cc3d88afc3b5d7400d', '[\"*\"]', '2025-10-29 22:25:31', NULL, '2025-10-29 21:54:43', '2025-10-29 22:25:31'),
(22, 'App\\Models\\User', 3, 'auth-token', 'b3129caf1b512ab8b924e22b2697fc265ce407a0fbf1265d57fb1159e75b591a', '[\"*\"]', '2025-10-29 22:27:20', NULL, '2025-10-29 22:27:18', '2025-10-29 22:27:20'),
(23, 'App\\Models\\User', 3, 'auth-token', 'bf01b240f8349fdf15ecfd0673daac4089e6e60e4c75cd55a5fad5489f400130', '[\"*\"]', '2025-10-29 22:28:07', NULL, '2025-10-29 22:27:44', '2025-10-29 22:28:07'),
(24, 'App\\Models\\User', 3, 'auth-token', 'c9e3f16825911d361c6a248c9384d9414d0ebbbdcd92c5325a4b4eefd1fbffb9', '[\"*\"]', '2025-10-29 22:33:36', NULL, '2025-10-29 22:30:38', '2025-10-29 22:33:36'),
(25, 'App\\Models\\User', 3, 'auth-token', '06b2da25f59d3c1ac736cb271a999a81b787fa28827877622311763cc0fc7f0f', '[\"*\"]', '2025-10-29 22:34:21', NULL, '2025-10-29 22:33:58', '2025-10-29 22:34:21'),
(26, 'App\\Models\\User', 3, 'auth-token', '610914f4c78fb6c62479c46546d2dda3e788a3f8a8879776d3725472363b55a7', '[\"*\"]', '2025-10-29 22:35:58', NULL, '2025-10-29 22:35:56', '2025-10-29 22:35:58'),
(27, 'App\\Models\\User', 3, 'auth-token', 'de101d4d588e7aa68a3909d12925d36be3a82b5f26fe81e5ee1028f43ddb4bf7', '[\"*\"]', '2025-10-29 23:05:26', NULL, '2025-10-29 22:36:52', '2025-10-29 23:05:26');

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
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `category_id`, `title`, `meta_title`, `meta_description`, `meta_keywords`, `og_title`, `og_description`, `og_image`, `canonical_url`, `schema_markup`, `ai_summary`, `faq_schema`, `seo_score`, `index_follow`, `slug`, `excerpt`, `content`, `featured_image`, `tags`, `is_premium`, `published`, `published_at`, `is_active`, `sort_order`, `views`, `reading_time`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'Getting Started with Vue 3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 'getting-started-vue3', 'Learn the basics of Vue 3 and build your first application', '<p>Vue 3 is the latest version of the progressive JavaScript framework. In this comprehensive guide, we will explore the new features including the Composition API, improved performance, and TypeScript support.</p><p>We will build a complete application from scratch, covering components, state management, routing, and deployment strategies. By the end of this tutorial, you will have a solid understanding of Vue 3 fundamentals.</p>', 'posts/vue3-tutorial.jpg', '[\"vue\",\"javascript\",\"frontend\",\"tutorial\"]', 0, 1, '2025-10-24 16:26:35', 1, 0, 150, 1, '2025-10-29 16:26:35', '2025-10-29 16:26:35', NULL),
(2, 1, 'Laravel 12 New Features', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 'laravel-12-new-features', 'Explore the exciting new features in Laravel 12', '<p>Laravel 12 brings significant improvements to the framework. We will dive deep into the new features including enhanced database query builder, improved testing utilities, and better performance.</p><p>This article covers practical examples and migration guides to help you upgrade your existing Laravel applications to version 12.</p>', 'posts/laravel-12.jpg', '[\"laravel\",\"php\",\"backend\",\"framework\"]', 0, 1, '2025-10-27 16:26:35', 1, 0, 89, 1, '2025-10-29 16:26:35', '2025-10-29 16:26:35', NULL);

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
(1, 1, 'en', 'Getting Started with Vue 3', 'getting-started-vue3', 'Learn the basics of Vue 3 and build your first application', '<p>Vue 3 is the latest version of the progressive JavaScript framework. In this comprehensive guide, we will explore the new features including the Composition API, improved performance, and TypeScript support.</p><p>We will build a complete application from scratch, covering components, state management, routing, and deployment strategies. By the end of this tutorial, you will have a solid understanding of Vue 3 fundamentals.</p>', 'Getting Started with Vue 3 - Complete Guide', 'Learn Vue 3 from scratch with this comprehensive tutorial covering Composition API, components, and more.', NULL, NULL, NULL, NULL, '2025-10-29 16:26:35', '2025-10-29 16:26:35'),
(2, 1, 'id', 'Memulai dengan Vue 3', 'memulai-dengan-vue3', 'Pelajari dasar-dasar Vue 3 dan bangun aplikasi pertama Anda', '<p>Vue 3 adalah versi terbaru dari framework JavaScript progresif. Dalam panduan komprehensif ini, kita akan menjelajahi fitur-fitur baru termasuk Composition API, peningkatan performa, dan dukungan TypeScript.</p><p>Kita akan membangun aplikasi lengkap dari awal, mencakup komponen, manajemen state, routing, dan strategi deployment. Di akhir tutorial ini, Anda akan memiliki pemahaman yang solid tentang fundamental Vue 3.</p>', 'Memulai dengan Vue 3 - Panduan Lengkap', 'Pelajari Vue 3 dari awal dengan tutorial komprehensif ini yang mencakup Composition API, komponen, dan lainnya.', NULL, NULL, NULL, NULL, '2025-10-29 16:26:35', '2025-10-29 16:26:35'),
(3, 2, 'en', 'Laravel 12 New Features', 'laravel-12-new-features', 'Explore the exciting new features in Laravel 12', '<p>Laravel 12 brings significant improvements to the framework. We will dive deep into the new features including enhanced database query builder, improved testing utilities, and better performance.</p><p>This article covers practical examples and migration guides to help you upgrade your existing Laravel applications to version 12.</p>', 'Laravel 12 New Features - Complete Overview', 'Discover the new features in Laravel 12 and learn how to upgrade your applications.', NULL, NULL, NULL, NULL, '2025-10-29 16:26:35', '2025-10-29 16:26:35'),
(4, 2, 'id', 'Fitur Baru Laravel 12', 'fitur-baru-laravel-12', 'Jelajahi fitur-fitur baru yang menarik di Laravel 12', '<p>Laravel 12 membawa peningkatan signifikan ke framework. Kita akan mendalami fitur-fitur baru termasuk query builder database yang ditingkatkan, utilitas testing yang lebih baik, dan performa yang lebih bagus.</p><p>Artikel ini mencakup contoh praktis dan panduan migrasi untuk membantu Anda mengupgrade aplikasi Laravel yang ada ke versi 12.</p>', 'Fitur Baru Laravel 12 - Overview Lengkap', 'Temukan fitur-fitur baru di Laravel 12 dan pelajari cara mengupgrade aplikasi Anda.', NULL, NULL, NULL, NULL, '2025-10-29 16:26:35', '2025-10-29 16:26:35');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` bigint(20) UNSIGNED NOT NULL,
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
  `tech_stack_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tech_stack_details`)),
  `seo_score` int(11) NOT NULL DEFAULT 0,
  `index_follow` tinyint(1) NOT NULL DEFAULT 1,
  `slug` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `content` longtext DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`)),
  `category` varchar(255) NOT NULL,
  `technologies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`technologies`)),
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `client` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `completed_at` date DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `published` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `cta_title` varchar(255) DEFAULT NULL,
  `cta_description` text DEFAULT NULL,
  `cta_button_text` varchar(100) DEFAULT NULL,
  `cta_phone_number` varchar(20) DEFAULT NULL,
  `related_project_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`related_project_ids`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `title`, `meta_title`, `meta_description`, `meta_keywords`, `og_title`, `og_description`, `og_image`, `canonical_url`, `schema_markup`, `ai_summary`, `tech_stack_details`, `seo_score`, `index_follow`, `slug`, `description`, `content`, `image`, `images`, `category`, `technologies`, `tags`, `client`, `url`, `completed_at`, `featured`, `published`, `is_active`, `sort_order`, `created_at`, `updated_at`, `deleted_at`, `cta_title`, `cta_description`, `cta_button_text`, `cta_phone_number`, `related_project_ids`) VALUES
(1, 'E-commerce Platform', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 'ecommerce-platform', 'A modern, scalable e-commerce platform built with Laravel and React', 'A comprehensive e-commerce solution featuring product management, shopping cart, payment integration, and admin dashboard. Built with Laravel 12 backend and React 18 frontend.', 'projects/ecommerce.jpg', '[\"projects\\/ecommerce\\/1.jpg\",\"projects\\/ecommerce\\/2.jpg\",\"projects\\/ecommerce\\/3.jpg\"]', 'web', '[\"Laravel\",\"React\",\"MySQL\",\"Redis\",\"Stripe\"]', NULL, 'ABC Corporation', 'https://example.com', '2024-06-30', 1, 1, 1, 1, '2025-10-29 16:26:35', '2025-10-29 16:26:35', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'Task Management App', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 'task-management-app', 'Collaborative task management tool for teams', 'A powerful task management application with real-time collaboration, project tracking, and team communication features.', 'projects/task-app.jpg', '[\"projects\\/task-app\\/1.jpg\",\"projects\\/task-app\\/2.jpg\"]', 'web', '[\"Laravel\",\"Vue.js\",\"WebSockets\",\"PostgreSQL\"]', NULL, 'Internal Project', 'https://tasks.example.com', NULL, 0, 1, 1, 2, '2025-10-29 16:26:35', '2025-10-29 16:26:35', NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `project_translations`
--

CREATE TABLE `project_translations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `project_id` bigint(20) UNSIGNED NOT NULL,
  `language` varchar(5) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
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
-- Dumping data for table `project_translations`
--

INSERT INTO `project_translations` (`id`, `project_id`, `language`, `title`, `slug`, `description`, `content`, `meta_title`, `meta_description`, `og_title`, `og_description`, `canonical_url`, `ai_summary`, `created_at`, `updated_at`) VALUES
(1, 1, 'en', 'E-commerce Platform', 'ecommerce-platform', 'A modern, scalable e-commerce platform built with Laravel and React', 'A comprehensive e-commerce solution featuring product management, shopping cart, payment integration, and admin dashboard. Built with Laravel 12 backend and React 18 frontend.', 'E-commerce Platform - Modern Shopping Solution', 'A modern, scalable e-commerce platform built with Laravel and React for ABC Corporation', 'E-commerce Platform', 'Modern shopping solution with Laravel and React', NULL, NULL, '2025-10-29 16:26:35', '2025-10-29 16:26:35'),
(2, 1, 'id', 'Platform E-commerce', 'platform-ecommerce', 'Platform e-commerce modern dan scalable yang dibangun dengan Laravel dan React', 'Solusi e-commerce komprehensif dengan manajemen produk, keranjang belanja, integrasi pembayaran, dan dashboard admin. Dibangun dengan backend Laravel 12 dan frontend React 18.', 'Platform E-commerce - Solusi Belanja Modern', 'Platform e-commerce modern dan scalable yang dibangun dengan Laravel dan React untuk ABC Corporation', 'Platform E-commerce', 'Solusi belanja modern dengan Laravel dan React', NULL, NULL, '2025-10-29 16:26:35', '2025-10-29 16:26:35'),
(3, 2, 'en', 'Task Management Application', 'task-management-app', 'Collaborative task management tool for teams', 'A powerful task management application with real-time collaboration, project tracking, and team communication features.', 'Task Management App - Team Collaboration Tool', 'Collaborative task management tool for teams with real-time updates', NULL, NULL, NULL, NULL, '2025-10-29 16:26:35', '2025-10-29 16:26:35'),
(4, 2, 'id', 'Aplikasi Manajemen Tugas', 'aplikasi-manajemen-tugas', 'Tool manajemen tugas kolaboratif untuk tim', 'Aplikasi manajemen tugas yang powerful dengan kolaborasi real-time, pelacakan proyek, dan fitur komunikasi tim.', 'Aplikasi Manajemen Tugas - Tool Kolaborasi Tim', 'Tool manajemen tugas kolaboratif untuk tim dengan pembaruan real-time', NULL, NULL, NULL, NULL, '2025-10-29 16:26:35', '2025-10-29 16:26:35');

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
(1, 'name', 'Ali Sadikin', 'about', 'text', '2025-10-30 03:01:58', '2025-10-30 03:01:58'),
(2, 'title', 'Full-Stack Developer & Digital Solutions Architect', 'about', 'text', '2025-10-30 03:01:58', '2025-10-30 03:01:58'),
(3, 'bio', '<p>I\'m a passionate full-stack developer with over 16 years of experience building innovative web applications and digital solutions. I specialize in modern JavaScript frameworks like Vue.js and React, backend development with Laravel and Node.js, and creating seamless user experiences.</p><p>My journey in software development has been driven by a constant desire to solve complex problems with elegant solutions. I believe in writing clean, maintainable code and staying at the forefront of technological advancements.</p>', 'about', 'textarea', '2025-10-30 03:01:58', '2025-10-30 03:01:58'),
(4, 'profile_photo', NULL, 'about', 'image', '2025-10-30 03:01:58', '2025-10-30 03:01:58'),
(5, 'skills', '[\"Vue.js\",\"React\",\"Laravel\",\"PHP\",\"Node.js\",\"TypeScript\",\"MySQL\",\"MongoDB\",\"Tailwind CSS\",\"Docker\",\"AWS\",\"Git\",\"REST API\",\"GraphQL\"]', 'about', 'json', '2025-10-30 03:01:58', '2025-10-30 03:01:58'),
(6, 'experience', '[{\"position\":\"Senior Full-Stack Developer\",\"company\":\"Tech Innovations Inc.\",\"period\":\"2020 - Present\",\"description\":\"Leading development of enterprise web applications using Vue.js and Laravel. Mentoring junior developers and architecting scalable solutions.\"},{\"position\":\"Full-Stack Developer\",\"company\":\"Digital Solutions Co.\",\"period\":\"2017 - 2020\",\"description\":\"Developed and maintained multiple client projects using modern web technologies. Implemented CI\\/CD pipelines and improved deployment processes.\"},{\"position\":\"Frontend Developer\",\"company\":\"Creative Web Agency\",\"period\":\"2015 - 2017\",\"description\":\"Created responsive and interactive user interfaces using Vue.js and React. Collaborated with designers to implement pixel-perfect designs.\"}]', 'about', 'json', '2025-10-30 03:01:58', '2025-10-30 03:01:58'),
(7, 'education', '[{\"degree\":\"Bachelor of Computer Science\",\"institution\":\"University of Technology\",\"period\":\"2011 - 2015\",\"description\":\"Specialized in Software Engineering and Web Technologies\"},{\"degree\":\"Certified AWS Solutions Architect\",\"institution\":\"Amazon Web Services\",\"period\":\"2021\",\"description\":\"Professional certification for cloud architecture and deployment\"}]', 'about', 'json', '2025-10-30 03:01:58', '2025-10-30 03:01:58'),
(8, 'social_links', '[{\"platform\":\"github\",\"url\":\"https:\\/\\/github.com\\/alisadikinma\"},{\"platform\":\"linkedin\",\"url\":\"https:\\/\\/linkedin.com\\/in\\/alisadikin\"},{\"platform\":\"twitter\",\"url\":\"https:\\/\\/twitter.com\\/alisadikin\"}]', 'about', 'json', '2025-10-30 03:01:58', '2025-10-30 03:01:58');

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
(3, 'Ali Sadikin', 'admin@alisadikinma.com', '2025-10-29 19:08:06', '$2y$12$X2NcYoE6T9UZ9/pr7Ij83.m1BIRd2zQCEYSdyTpuOCfFurk1oXFTe', NULL, '2025-10-29 19:08:06', '2025-10-29 19:08:06'),
(4, 'Admin', 'admin@portfolio.com', '2025-10-29 19:08:06', '$2y$12$uD.a3Vj36yv7eOWeq3dW4Oo0Wh9Vr8vqqqwtXgOR/0HALzGRIdPk6', NULL, '2025-10-29 19:08:06', '2025-10-29 19:08:06');

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
  ADD KEY `galleries_sort_order_index` (`sort_order`),
  ADD KEY `galleries_award_id_foreign` (`award_id`);

--
-- Indexes for table `gallery_items`
--
ALTER TABLE `gallery_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gallery_items_gallery_id_sequence_index` (`gallery_id`,`sequence`);

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
  ADD UNIQUE KEY `projects_slug_unique` (`slug`),
  ADD KEY `projects_meta_title_index` (`meta_title`),
  ADD KEY `projects_seo_score_index` (`seo_score`),
  ADD KEY `projects_index_follow_index` (`index_follow`),
  ADD KEY `projects_is_active_index` (`is_active`),
  ADD KEY `projects_sort_order_index` (`sort_order`);

--
-- Indexes for table `project_translations`
--
ALTER TABLE `project_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_translations_project_id_language_unique` (`project_id`,`language`),
  ADD KEY `project_translations_language_index` (`language`),
  ADD KEY `project_translations_slug_index` (`slug`);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `automation_logs`
--
ALTER TABLE `automation_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `awards`
--
ALTER TABLE `awards`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `award_gallery`
--
ALTER TABLE `award_gallery`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `gallery_items`
--
ALTER TABLE `gallery_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `newsletters`
--
ALTER TABLE `newsletters`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `page_sections`
--
ALTER TABLE `page_sections`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `post_translations`
--
ALTER TABLE `post_translations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `project_translations`
--
ALTER TABLE `project_translations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `automation_logs`
--
ALTER TABLE `automation_logs`
  ADD CONSTRAINT `automation_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `award_gallery`
--
ALTER TABLE `award_gallery`
  ADD CONSTRAINT `award_gallery_award_id_foreign` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `award_gallery_gallery_id_foreign` FOREIGN KEY (`gallery_id`) REFERENCES `galleries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `galleries`
--
ALTER TABLE `galleries`
  ADD CONSTRAINT `galleries_award_id_foreign` FOREIGN KEY (`award_id`) REFERENCES `awards` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `gallery_items`
--
ALTER TABLE `gallery_items`
  ADD CONSTRAINT `gallery_items_gallery_id_foreign` FOREIGN KEY (`gallery_id`) REFERENCES `galleries` (`id`) ON DELETE CASCADE;

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
  ADD CONSTRAINT `project_translations_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
