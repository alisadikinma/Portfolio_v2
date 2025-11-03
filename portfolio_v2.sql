-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 03, 2025 at 10:43 AM
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
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `automation_logs`
--

INSERT INTO `automation_logs` (`id`, `user_id`, `token_id`, `action`, `ip_address`, `user_agent`, `metadata`, `created_at`) VALUES
(1, 1, 6, 'images.batch_upload', '::1', 'curl/8.14.1', '{\"total_requested\":3,\"uploaded\":3,\"failed\":0}', '2025-11-02 04:16:26'),
(2, 1, 6, 'images.batch_upload', '::1', 'curl/8.14.1', '{\"total_requested\":1,\"uploaded\":1,\"failed\":0}', '2025-11-02 04:16:46');

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
(7, '1st Place Winner Nextdev Startup Competition', '<p>Achieved first place in prestigious Nextdev Startup Competition, demonstrating innovative digital solutions and entrepreneurial excellence.</p>', 'TELKOMSEL', 'AWARD-1761931598104-URUUXI', 'https://credentials.portfolio.com/verify/AWARD-1761931598104-URUUXI', '/uploads/awards/1761931643_idhRyVhh05_logos 2.png', '2018', 1, 1, '2025-10-31 10:27:23', '2025-10-31 10:27:23'),
(8, '1st Place Winner Wild Card Fenox Startup World Cup Competition', '<p>Won first place in Wild Card category of Fenox Startup World Cup Competition, showcasing global-level innovation and business excellence.</p>', 'FENOX - BEKRAF', 'AWARD-1761931648348-IADKJH', 'https://credentials.portfolio.com/verify/AWARD-1761931648348-IADKJH', '/uploads/awards/1761931691_image 1046.png', '2017', 1, 2, '2025-10-31 10:28:11', '2025-10-31 10:28:11'),
(9, 'Top 8 Finalist – IDBYTE 2017 Connected', '<p>From hundreds of visionary startups, only eight rose to the stage—and we were one of them. As a <strong>Top 8 Finalist</strong>, we showcased how bold ideas and cutting-edge innovation can shape the future of Indonesia’s digital landscape. This milestone wasn’t just recognition; it was proof that creativity, technology, and impact can truly connect to inspire a new generation of entrepreneurs. 🚀✨</p>', 'BUBU.com', 'AWARD-1761931696136-OGBMGB', 'https://credentials.portfolio.com/verify/AWARD-1761931696136-OGBMGB', '/uploads/awards/1761931761_bubu_com_awards.png', '2017', 1, 3, '2025-10-31 10:29:21', '2025-10-31 10:29:21'),
(10, 'Alibaba efounders Fellowship, Hangzhou China', '<p>Alibaba eFounders Fellowship (Class 6) by Alibaba Business School–UNCTAD brought 48 Southeast Asian founders (16 Indonesian) to Hangzhou (2–12 Jun 2019) for an immersion in Alibaba’s digital ecosystem to drive inclusive digital growth.</p>', 'Alibaba - UNCTAD', 'AWARD-1761931769462-T3KDT6', 'https://credentials.portfolio.com/verify/AWARD-1761931769462-T3KDT6', '/uploads/awards/1761931812_alibaba-icon 1.png', '2019', 1, 4, '2025-10-31 10:30:12', '2025-10-31 10:30:12'),
(11, 'Google Startup Grind Silicon Valley - San Fransisco', '<p>Startup Grind Global Conference 2018 in Silicon Valley—hosted by Startup Grind with Google for Entrepreneurs (and partners Oracle, Intuit, SVB)—gathered founders and investors for keynotes, fireside chats, tactical workshops, and high-value networking focused on product, growth, and fundraising.</p>', 'Google', 'AWARD-1761931822669-WH2XSB', 'https://credentials.portfolio.com/verify/AWARD-1761931822669-WH2XSB', '/uploads/awards/1761931864_Logo.png', '2018', 1, 5, '2025-10-31 10:31:04', '2025-10-31 10:31:04'),
(12, 'Google Project Management: Professional Certificate', '<p>Completed the Google Project Management Professional Certificate (Jul 2024), covering six key areas: foundations, project initiation, planning, execution, agile methodologies, and a real-world capstone. This certification validates end-to-end project management skills and the ability to apply both traditional and agile approaches effectively.</p>', 'Google', 'AWARD-1761931871897-S3JNTC', 'https://credentials.portfolio.com/verify/AWARD-1761931871897-S3JNTC', '/uploads/awards/1761931928_google_project_management.png', '2024', 1, 6, '2025-10-31 10:32:08', '2025-10-31 10:32:08');

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
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta_title` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_keywords` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `og_title` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `og_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `og_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `canonical_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schema_markup` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `index_follow` tinyint(1) NOT NULL DEFAULT 1,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#3B82F6',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `meta_title`, `meta_description`, `meta_keywords`, `og_title`, `og_description`, `og_image`, `canonical_url`, `schema_markup`, `index_follow`, `slug`, `description`, `color`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Web Development', 'Web Development Articles', 'Read the latest articles about web development, programming languages, frameworks, and best practices.', NULL, NULL, NULL, NULL, NULL, NULL, 1, 'web-development', 'Articles about web development, programming, and coding', '#3b82f6', 1, 0, '2025-10-31 10:07:55', '2025-10-31 10:07:55'),
(2, 'Design', 'Design Articles', 'Explore articles about UI/UX design, graphic design, and creative processes.', NULL, NULL, NULL, NULL, NULL, NULL, 1, 'design', 'UI/UX design, graphic design, and creative inspiration', '#ec4899', 1, 0, '2025-10-31 10:07:55', '2025-10-31 10:07:55'),
(3, 'Technology', 'Technology News', 'Stay updated with the latest technology news, trends, and innovations.', NULL, NULL, NULL, NULL, NULL, NULL, 1, 'technology', 'Latest tech news, trends, and innovations', '#10b981', 1, 0, '2025-10-31 10:07:55', '2025-10-31 10:07:55'),
(4, 'Tutorial', 'Tutorials and Guides', 'Learn with our comprehensive tutorials and step-by-step guides.', NULL, NULL, NULL, NULL, NULL, NULL, 1, 'tutorial', 'Step-by-step guides and tutorials', '#f59e0b', 1, 0, '2025-10-31 10:07:55', '2025-10-31 10:07:55'),
(5, 'Career', 'Career Development', 'Get career advice, tips, and insights for professional growth.', NULL, NULL, NULL, NULL, NULL, NULL, 1, 'career', 'Career advice, tips, and professional development', '#8b5cf6', 1, 0, '2025-10-31 10:07:55', '2025-10-31 10:07:55'),
(6, 'Personal', 'Personal Blog', 'Read personal thoughts, experiences, and stories.', NULL, NULL, NULL, NULL, NULL, NULL, 1, 'personal', 'Personal thoughts, experiences, and stories', '#ef4444', 1, 0, '2025-10-31 10:07:55', '2025-10-31 10:07:55'),
(7, 'Tutorials', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'tutorials', 'Step-by-step guides and tutorials', '#10B981', 1, 2, '2025-10-31 10:07:55', '2025-10-31 10:07:55');

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `whatsapp_number` varchar(20) DEFAULT NULL,
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
(9, '1st Place Winner Nextdev Startup Competition', 'Recognized as Telkomsel’s flagship startup program, NextDev empowers young innovators to create impactful digital solutions. Winning 1st place highlights innovation, vision, and execution in driving Indonesia’s digital transformation and shaping future global leaders.', NULL, NULL, 'gallery/thumbnails/1761930891_thumb_1st-place-winner-nextdev-startup-competition.png', 7, 1, 0, '2025-10-31 10:14:51', '2025-10-31 10:27:24'),
(10, '1st Wild Card Winner – Startup World Cup', 'Organized by Fenox Venture Capital in collaboration with Indonesia’s Creative Economy Agency (BEKRAF), the Startup World Cup is a prestigious global competition connecting startups with top investors and tech leaders. Winning the Wild Card Round in Indonesia highlighted innovation, resilience, and global potential—granting the opportunity to join the regional finals and gain exposure to Silicon Valley’s ecosystem.', NULL, NULL, 'gallery/thumbnails/1761931360_thumb_1st-wild-card-winner-startup-world-cup.png', 8, 1, 0, '2025-10-31 10:22:40', '2025-10-31 10:28:12'),
(11, 'Top 8 Finalist – IDBYTE 2017 Connected', 'From hundreds of visionary startups, only eight rose to the stage—and we were one of them. As a Top 8 Finalist, we showcased how bold ideas and cutting-edge innovation can shape the future of Indonesia’s digital landscape. This milestone wasn’t just recognition; it was proof that creativity, technology, and impact can truly connect to inspire a new generation of entrepreneurs. 🚀✨', NULL, NULL, 'gallery/thumbnails/1761931395_thumb_top-8-finalist-idbyte-2017-connected.png', 9, 1, 0, '2025-10-31 10:23:15', '2025-10-31 10:29:22'),
(12, 'Alibaba eFounder Fellowship', 'Alibaba eFounders Fellowship is a joint Alibaba Business School–UNCTAD program that equips digital entrepreneurs from developing countries to build inclusive, SDG-aligned “new economy” ventures through an immersive curriculum and community. ([UNCTAD][1]) The core learning is a 12-day, hands-on study tour at Alibaba’s Hangzhou HQ—lectures, site visits, and deep dives into the Alibaba ecosystem—followed by a two-year impact commitment and an active alumni network. ([UNCTAD][1])\r\nIn Class 6 (2–12 June 2019), 48 founders from six Southeast Asian countries graduated; Indonesia contributed the largest cohort with 16 fellows. Collectively, fellows had created 2,700+ jobs, generated \\~US\\$400M in annual revenue, and served 7M+ businesses and consumers. ([id.alibabanews.com][2])', NULL, NULL, 'gallery/thumbnails/1761931433_thumb_alibaba-efounder-fellowship.png', 10, 1, 0, '2025-10-31 10:23:53', '2025-10-31 10:30:14'),
(13, '🚀 Google Startup Grind Global Conference 2018', 'Backed by GOOGLE for Entrepreneurs, this is one of the world’s largest startup gatherings in Silicon Valley. Thousands of founders, investors, and innovators came together to learn, share, and build global connections.\r\n\r\nWith support from Google, Oracle, Intuit, and Silicon Valley Bank, the conference became a hub for groundbreaking ideas, high-level networking, and exclusive insights into the future of startups.\r\n\r\n👉 A stage where local innovators connect with the global ecosystem — unlocking opportunities for collaboration, investment, and inspiration.', NULL, NULL, 'gallery/thumbnails/1761931470_thumb_google-startup-grind-global-conference-2018.png', 11, 1, 0, '2025-10-31 10:24:30', '2025-10-31 10:31:06');

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
(18, 9, 'image', 'gallery/items/1761930891_item_9_0.jpg', '1st Place Winner Nextdev Startup Competition - Image 1', NULL, 0, '2025-10-31 10:14:51', '2025-10-31 10:14:51'),
(19, 9, 'image', 'gallery/items/1761930891_item_9_1.jpg', '1st Place Winner Nextdev Startup Competition - Image 2', NULL, 1, '2025-10-31 10:14:51', '2025-10-31 10:14:51'),
(20, 9, 'image', 'gallery/items/1761930891_item_9_2.jpg', '1st Place Winner Nextdev Startup Competition - Image 3', NULL, 2, '2025-10-31 10:14:51', '2025-10-31 10:14:51'),
(21, 9, 'image', 'gallery/items/1761930891_item_9_3.jpg', '1st Place Winner Nextdev Startup Competition - Image 4', NULL, 3, '2025-10-31 10:14:51', '2025-10-31 10:14:51'),
(22, 9, 'image', 'gallery/items/1761930891_item_9_4.jpg', '1st Place Winner Nextdev Startup Competition - Image 5', NULL, 4, '2025-10-31 10:14:51', '2025-10-31 10:14:51'),
(23, 10, 'image', 'gallery/items/1761931360_item_10_0.jpg', '1st Wild Card Winner – Startup World Cup - Image 1', NULL, 0, '2025-10-31 10:22:40', '2025-10-31 10:22:40'),
(24, 10, 'image', 'gallery/items/1761931360_item_10_1.jpg', '1st Wild Card Winner – Startup World Cup - Image 2', NULL, 1, '2025-10-31 10:22:40', '2025-10-31 10:22:40'),
(25, 10, 'image', 'gallery/items/1761931360_item_10_2.jpg', '1st Wild Card Winner – Startup World Cup - Image 3', NULL, 2, '2025-10-31 10:22:40', '2025-10-31 10:22:40'),
(26, 10, 'image', 'gallery/items/1761931360_item_10_3.jpg', '1st Wild Card Winner – Startup World Cup - Image 4', NULL, 3, '2025-10-31 10:22:40', '2025-10-31 10:22:40'),
(27, 11, 'image', 'gallery/items/1761931395_item_11_0.jpg', 'Top 8 Finalist – IDBYTE 2017 Connected - Image 1', NULL, 0, '2025-10-31 10:23:15', '2025-10-31 10:23:15'),
(28, 11, 'image', 'gallery/items/1761931395_item_11_1.jpg', 'Top 8 Finalist – IDBYTE 2017 Connected - Image 2', NULL, 1, '2025-10-31 10:23:15', '2025-10-31 10:23:15'),
(29, 11, 'image', 'gallery/items/1761931395_item_11_2.jpg', 'Top 8 Finalist – IDBYTE 2017 Connected - Image 3', NULL, 2, '2025-10-31 10:23:15', '2025-10-31 10:23:15'),
(30, 11, 'image', 'gallery/items/1761931395_item_11_3.jpg', 'Top 8 Finalist – IDBYTE 2017 Connected - Image 4', NULL, 3, '2025-10-31 10:23:15', '2025-10-31 10:23:15'),
(31, 11, 'image', 'gallery/items/1761931395_item_11_4.jpg', 'Top 8 Finalist – IDBYTE 2017 Connected - Image 5', NULL, 4, '2025-10-31 10:23:15', '2025-10-31 10:23:15'),
(32, 11, 'image', 'gallery/items/1761931395_item_11_5.jpg', 'Top 8 Finalist – IDBYTE 2017 Connected - Image 6', NULL, 5, '2025-10-31 10:23:15', '2025-10-31 10:23:15'),
(33, 11, 'image', 'gallery/items/1761931395_item_11_6.jpg', 'Top 8 Finalist – IDBYTE 2017 Connected - Image 7', NULL, 6, '2025-10-31 10:23:15', '2025-10-31 10:23:15'),
(34, 12, 'image', 'gallery/items/1761931433_item_12_0.jpg', 'Alibaba eFounder Fellowship - Image 1', NULL, 0, '2025-10-31 10:23:53', '2025-10-31 10:23:53'),
(35, 12, 'image', 'gallery/items/1761931433_item_12_1.jpg', 'Alibaba eFounder Fellowship - Image 2', NULL, 1, '2025-10-31 10:23:53', '2025-10-31 10:23:53'),
(36, 12, 'image', 'gallery/items/1761931433_item_12_2.jpg', 'Alibaba eFounder Fellowship - Image 3', NULL, 2, '2025-10-31 10:23:53', '2025-10-31 10:23:53'),
(37, 12, 'image', 'gallery/items/1761931433_item_12_3.jpg', 'Alibaba eFounder Fellowship - Image 4', NULL, 3, '2025-10-31 10:23:53', '2025-10-31 10:23:53'),
(38, 12, 'image', 'gallery/items/1761931433_item_12_4.jpg', 'Alibaba eFounder Fellowship - Image 5', NULL, 4, '2025-10-31 10:23:53', '2025-10-31 10:23:53'),
(39, 12, 'image', 'gallery/items/1761931433_item_12_5.jpg', 'Alibaba eFounder Fellowship - Image 6', NULL, 5, '2025-10-31 10:23:53', '2025-10-31 10:23:53'),
(40, 12, 'image', 'gallery/items/1761931433_item_12_6.jpg', 'Alibaba eFounder Fellowship - Image 7', NULL, 6, '2025-10-31 10:23:53', '2025-10-31 10:23:53'),
(41, 12, 'image', 'gallery/items/1761931433_item_12_7.jpg', 'Alibaba eFounder Fellowship - Image 8', NULL, 7, '2025-10-31 10:23:53', '2025-10-31 10:23:53'),
(42, 12, 'image', 'gallery/items/1761931433_item_12_8.jpg', 'Alibaba eFounder Fellowship - Image 9', NULL, 8, '2025-10-31 10:23:53', '2025-10-31 10:23:53'),
(43, 12, 'image', 'gallery/items/1761931433_item_12_9.jpg', 'Alibaba eFounder Fellowship - Image 10', NULL, 9, '2025-10-31 10:23:53', '2025-10-31 10:23:53'),
(44, 12, 'image', 'gallery/items/1761931433_item_12_10.jpg', 'Alibaba eFounder Fellowship - Image 11', NULL, 10, '2025-10-31 10:23:53', '2025-10-31 10:23:53'),
(45, 13, 'image', 'gallery/items/1761931470_item_13_0.png', '🚀 Google Startup Grind Global Conference 2018 - Image 1', NULL, 0, '2025-10-31 10:24:30', '2025-10-31 10:24:30'),
(46, 13, 'image', 'gallery/items/1761931470_item_13_1.png', '🚀 Google Startup Grind Global Conference 2018 - Image 2', NULL, 1, '2025-10-31 10:24:30', '2025-10-31 10:24:30'),
(47, 13, 'image', 'gallery/items/1761931470_item_13_2.png', '🚀 Google Startup Grind Global Conference 2018 - Image 3', NULL, 2, '2025-10-31 10:24:30', '2025-10-31 10:24:30'),
(48, 13, 'image', 'gallery/items/1761931470_item_13_3.jpg', '🚀 Google Startup Grind Global Conference 2018 - Image 4', NULL, 3, '2025-10-31 10:24:30', '2025-10-31 10:24:30'),
(49, 13, 'image', 'gallery/items/1761931470_item_13_4.jpg', '🚀 Google Startup Grind Global Conference 2018 - Image 5', NULL, 4, '2025-10-31 10:24:30', '2025-10-31 10:24:30'),
(50, 13, 'image', 'gallery/items/1761931470_item_13_5.jpg', '🚀 Google Startup Grind Global Conference 2018 - Image 6', NULL, 5, '2025-10-31 10:24:30', '2025-10-31 10:24:30');

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
(1, 'Home', 'home', '/', 'home', 1, 0, '2025-10-31 10:07:55', '2025-10-31 17:21:31'),
(2, 'About', 'about', '/about', 'information-circle', 1, 1, '2025-10-31 10:07:55', '2025-10-31 17:21:31'),
(3, 'Projects', 'projects', '/projects', 'briefcase', 1, 2, '2025-10-31 10:07:55', '2025-10-31 17:21:31'),
(4, 'Awards', 'awards', '/awards', 'star', 0, 4, '2025-10-31 10:07:55', '2025-10-31 17:21:46'),
(5, 'Blog', 'blog', '/blog', 'newspaper', 0, 5, '2025-10-31 10:07:55', '2025-11-02 07:11:33'),
(6, 'Gallery', 'gallery', '/gallery', 'photograph', 1, 3, '2025-10-31 10:07:55', '2025-10-31 17:21:55'),
(7, 'Contact', 'contact', '/contact', 'mail', 1, 6, '2025-10-31 10:07:55', '2025-10-31 17:21:31');

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
(35, '2025_10_30_000001_change_received_at_to_string_in_awards_table', 1),
(36, '2025_11_02_120000_add_missing_fields_to_projects_table', 2),
(37, '2025_11_02_000001_add_content_field_to_projects_table', 3),
(38, '2025_11_03_000001_add_whatsapp_number_to_contacts_table', 4),
(40, '2025_11_03_072451_add_project_template_fields_to_projects_table', 5);

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
(1, 'homepage', 'hero', 1, 0, '2025-10-31 10:07:55', '2025-10-31 10:08:27'),
(2, 'homepage', 'featured_projects', 1, 3, '2025-10-31 10:07:55', '2025-10-31 10:08:27'),
(3, 'homepage', 'latest_blog', 0, 4, '2025-10-31 10:07:55', '2025-11-02 07:12:07'),
(4, 'homepage', 'awards', 1, 1, '2025-10-31 10:07:55', '2025-10-31 17:21:15'),
(5, 'homepage', 'gallery', 0, 2, '2025-10-31 10:07:55', '2025-10-31 17:21:18'),
(6, 'homepage', 'testimonials', 0, 5, '2025-10-31 10:07:55', '2025-10-31 10:08:39'),
(7, 'homepage', 'cta', 1, 6, '2025-10-31 10:07:55', '2025-11-02 07:12:03'),
(8, 'about', 'featured_projects', 0, 0, '2025-10-31 10:07:55', '2025-10-31 10:07:55'),
(9, 'about', 'latest_blog', 0, 1, '2025-10-31 10:07:55', '2025-10-31 10:07:55'),
(10, 'about', 'cta', 0, 2, '2025-10-31 10:07:55', '2025-10-31 10:07:55'),
(11, 'projects', 'latest_blog', 0, 0, '2025-10-31 10:07:55', '2025-10-31 10:07:55'),
(12, 'projects', 'cta', 1, 1, '2025-10-31 10:07:55', '2025-11-02 07:42:32'),
(13, 'blog', 'featured_projects', 0, 0, '2025-10-31 10:07:55', '2025-10-31 10:07:55'),
(14, 'blog', 'cta', 0, 1, '2025-10-31 10:07:55', '2025-10-31 10:07:55');

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
(2, 'App\\Models\\User', 1, 'auth-token', '1c5524dfad6f31aa6268b5572d60c211771db24cc52abd5889ffc0be5d19f4ae', '[\"*\"]', '2025-11-02 03:30:23', NULL, '2025-11-02 03:24:26', '2025-11-02 03:30:23'),
(3, 'App\\Models\\User', 1, 'n8n-blogging', 'fee5fe570b9c06eb073f55afdc7ea47049e5b2cb29867a2979b9e1718133d9bc', '[\"post:read\",\"post:write\",\"post:delete\",\"category:read\"]', NULL, NULL, '2025-11-02 03:24:50', '2025-11-02 03:24:50'),
(4, 'App\\Models\\User', 1, 'auth-token', 'f09031e83de7c753344a12c349fc5335f2110ad63240c8255e53be021e00f729', '[\"*\"]', '2025-11-02 03:31:11', NULL, '2025-11-02 03:30:32', '2025-11-02 03:31:11'),
(6, 'App\\Models\\User', 1, 'api-n8n-blogging', 'c7c9a9c0bbcfa3a0b9130e48fd0901f443253d585d5119240abdfc7464bd7199', '[\"post:read\",\"post:write\",\"post:delete\",\"category:read\"]', '2025-11-02 04:17:56', NULL, '2025-11-02 03:31:11', '2025-11-02 04:17:56'),
(7, 'App\\Models\\User', 1, 'auth-token', '3cfb481b42aec7dcecc139f5e98265694f350558838bb564a6055fb0dfb2e4d6', '[\"*\"]', '2025-11-02 07:55:09', NULL, '2025-11-02 07:08:13', '2025-11-02 07:55:09'),
(8, 'App\\Models\\User', 1, 'auth-token', 'e2070c823212d241143471c173e77c37222b47f6e3c1fcdfd09620220ad97e87', '[\"*\"]', '2025-11-02 08:38:51', NULL, '2025-11-02 07:55:26', '2025-11-02 08:38:51'),
(9, 'App\\Models\\User', 1, 'auth-token', '13e61b31169248562a909e4325eff304985bbb5269a8ab45c8da1b8149dfb543', '[\"*\"]', '2025-11-02 09:02:21', NULL, '2025-11-02 08:39:29', '2025-11-02 09:02:21'),
(10, 'App\\Models\\User', 1, 'auth-token', '3afe4b246512487590f0507d6e74e1d46c6c2a13d31269d8a4a54923e81a9fb2', '[\"*\"]', '2025-11-02 09:44:12', NULL, '2025-11-02 09:02:34', '2025-11-02 09:44:12'),
(11, 'App\\Models\\User', 1, 'auth-token', '205f52c9b642932dc91329fdd321da7fb97fb44e77caa7598c5108879c2e721e', '[\"*\"]', '2025-11-02 10:05:48', NULL, '2025-11-02 09:44:24', '2025-11-02 10:05:48'),
(12, 'App\\Models\\User', 1, 'auth-token', 'ae530a39f58512a721bb665a41473644ec1497b2866500af4b1097798d6bf254', '[\"*\"]', '2025-11-02 11:25:38', NULL, '2025-11-02 10:21:12', '2025-11-02 11:25:38'),
(13, 'App\\Models\\User', 1, 'auth-token', '36faf12b30881f42ba529a1b9846d71ac75f07471d8f527acf083d05e4182a60', '[\"*\"]', '2025-11-02 20:40:24', NULL, '2025-11-02 20:39:54', '2025-11-02 20:40:24'),
(14, 'App\\Models\\User', 1, 'auth-token', '35056cb32ef5a5e59534531caab5c1eff285b549842de8571120159116cbdba8', '[\"*\"]', '2025-11-02 19:23:14', NULL, '2025-11-02 19:22:56', '2025-11-02 19:23:14'),
(15, 'App\\Models\\User', 1, 'auth-token', 'f2706465321d1fce8e72f5f893c201ad29b222816f5f7664504aab76f6b85312', '[\"*\"]', '2025-11-02 19:32:17', NULL, '2025-11-02 19:31:57', '2025-11-02 19:32:17'),
(16, 'App\\Models\\User', 1, 'auth-token', '72c5402d3634c3f47eaa4770b0caa32204001b86616c82fa44e78ca03d94fefa', '[\"*\"]', '2025-11-03 01:44:17', NULL, '2025-11-03 00:36:36', '2025-11-03 01:44:17'),
(17, 'App\\Models\\User', 1, 'auth-token', '5e919ed42aabfeaff3d284f90c759e9e224a4892e88f1af59f48c52c12e0e0e8', '[\"*\"]', '2025-11-03 02:16:13', NULL, '2025-11-03 01:44:26', '2025-11-03 02:16:13'),
(18, 'App\\Models\\User', 1, 'auth-token', '336a9f398ba7638ee3810c877e4d3bceb72bfb738b4ab3955e024b0e938d76e6', '[\"*\"]', '2025-11-03 02:43:35', NULL, '2025-11-03 02:35:42', '2025-11-03 02:43:35');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta_title` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_keywords` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `og_title` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `og_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `og_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `canonical_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schema_markup` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `ai_summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `faq_schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `seo_score` int(11) NOT NULL DEFAULT 0,
  `index_follow` tinyint(1) NOT NULL DEFAULT 1,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `excerpt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `featured_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `category_id`, `title`, `meta_title`, `meta_description`, `meta_keywords`, `og_title`, `og_description`, `og_image`, `canonical_url`, `schema_markup`, `ai_summary`, `faq_schema`, `seo_score`, `index_follow`, `slug`, `excerpt`, `content`, `featured_image`, `tags`, `is_premium`, `published`, `published_at`, `is_active`, `sort_order`, `views`, `reading_time`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'Getting Started with Vue 3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 'getting-started-vue3', 'Learn the basics of Vue 3 and build your first application', '<p>Vue 3 is the latest version of the progressive JavaScript framework. In this comprehensive guide, we will explore the new features including the Composition API, improved performance, and TypeScript support.</p><p>We will build a complete application from scratch, covering components, state management, routing, and deployment strategies. By the end of this tutorial, you will have a solid understanding of Vue 3 fundamentals.</p>', 'posts/vue3-tutorial.jpg', '[\"vue\",\"javascript\",\"frontend\",\"tutorial\"]', 0, 1, '2025-10-26 10:07:55', 1, 0, 150, 1, '2025-10-31 10:07:55', '2025-10-31 10:07:55', NULL),
(2, 1, 'Laravel 12 New Features', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 'laravel-12-new-features', 'Explore the exciting new features in Laravel 12', '<p>Laravel 12 brings significant improvements to the framework. We will dive deep into the new features including enhanced database query builder, improved testing utilities, and better performance.</p><p>This article covers practical examples and migration guides to help you upgrade your existing Laravel applications to version 12.</p>', 'posts/laravel-12.jpg', '[\"laravel\",\"php\",\"backend\",\"framework\"]', 0, 1, '2025-10-29 10:07:55', 1, 0, 89, 1, '2025-10-31 10:07:55', '2025-10-31 10:07:55', NULL);

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
(1, 1, 'en', 'Getting Started with Vue 3', 'getting-started-vue3', 'Learn the basics of Vue 3 and build your first application', '<p>Vue 3 is the latest version of the progressive JavaScript framework. In this comprehensive guide, we will explore the new features including the Composition API, improved performance, and TypeScript support.</p><p>We will build a complete application from scratch, covering components, state management, routing, and deployment strategies. By the end of this tutorial, you will have a solid understanding of Vue 3 fundamentals.</p>', 'Getting Started with Vue 3 - Complete Guide', 'Learn Vue 3 from scratch with this comprehensive tutorial covering Composition API, components, and more.', NULL, NULL, NULL, NULL, '2025-10-31 10:07:55', '2025-10-31 10:07:55'),
(2, 1, 'id', 'Memulai dengan Vue 3', 'memulai-dengan-vue3', 'Pelajari dasar-dasar Vue 3 dan bangun aplikasi pertama Anda', '<p>Vue 3 adalah versi terbaru dari framework JavaScript progresif. Dalam panduan komprehensif ini, kita akan menjelajahi fitur-fitur baru termasuk Composition API, peningkatan performa, dan dukungan TypeScript.</p><p>Kita akan membangun aplikasi lengkap dari awal, mencakup komponen, manajemen state, routing, dan strategi deployment. Di akhir tutorial ini, Anda akan memiliki pemahaman yang solid tentang fundamental Vue 3.</p>', 'Memulai dengan Vue 3 - Panduan Lengkap', 'Pelajari Vue 3 dari awal dengan tutorial komprehensif ini yang mencakup Composition API, komponen, dan lainnya.', NULL, NULL, NULL, NULL, '2025-10-31 10:07:55', '2025-10-31 10:07:55'),
(3, 2, 'en', 'Laravel 12 New Features', 'laravel-12-new-features', 'Explore the exciting new features in Laravel 12', '<p>Laravel 12 brings significant improvements to the framework. We will dive deep into the new features including enhanced database query builder, improved testing utilities, and better performance.</p><p>This article covers practical examples and migration guides to help you upgrade your existing Laravel applications to version 12.</p>', 'Laravel 12 New Features - Complete Overview', 'Discover the new features in Laravel 12 and learn how to upgrade your applications.', NULL, NULL, NULL, NULL, '2025-10-31 10:07:55', '2025-10-31 10:07:55'),
(4, 2, 'id', 'Fitur Baru Laravel 12', 'fitur-baru-laravel-12', 'Jelajahi fitur-fitur baru yang menarik di Laravel 12', '<p>Laravel 12 membawa peningkatan signifikan ke framework. Kita akan mendalami fitur-fitur baru termasuk query builder database yang ditingkatkan, utilitas testing yang lebih baik, dan performa yang lebih bagus.</p><p>Artikel ini mencakup contoh praktis dan panduan migrasi untuk membantu Anda mengupgrade aplikasi Laravel yang ada ke versi 12.</p>', 'Fitur Baru Laravel 12 - Overview Lengkap', 'Temukan fitur-fitur baru di Laravel 12 dan pelajari cara mengupgrade aplikasi Anda.', NULL, NULL, NULL, NULL, '2025-10-31 10:07:55', '2025-10-31 10:07:55');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta_title` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_keywords` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `og_title` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `og_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `og_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `canonical_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schema_markup` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `ai_summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tech_stack_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `seo_score` int(11) NOT NULL DEFAULT 0,
  `index_follow` tinyint(1) NOT NULL DEFAULT 1,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `impact_statement` varchar(255) DEFAULT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `context` text DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `problem` text DEFAULT NULL,
  `solution` text DEFAULT NULL,
  `integration` text DEFAULT NULL,
  `result` text DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `domain` varchar(100) DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `technologies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `client` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `github_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completed_at` date DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `published` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `related_project_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `title`, `meta_title`, `meta_description`, `meta_keywords`, `og_title`, `og_description`, `og_image`, `canonical_url`, `schema_markup`, `ai_summary`, `tech_stack_details`, `seo_score`, `index_follow`, `slug`, `description`, `impact_statement`, `content`, `context`, `role`, `problem`, `solution`, `integration`, `result`, `image`, `images`, `category`, `domain`, `status`, `technologies`, `tags`, `client`, `url`, `github_url`, `completed_at`, `start_date`, `end_date`, `featured`, `published`, `is_active`, `sort_order`, `created_at`, `updated_at`, `deleted_at`, `related_project_ids`) VALUES
(1, 'Production Ai-Powered Metal Walk-Through Monitoring Cctv', 'Production Ai-Powered Metal Walk-Through Monitoring Cctv', 'An advanced AI-powered solution designed to enhance operational efficiency and quality control.', 'Computer Vision, Quality Control, Real-time Monitoring, Sensors, Production, AI & Machine Learning, SATNUSA, Industrial Solutions', 'Production Ai-Powered Metal Walk-Through Monito... | SATNUSA', 'An advanced AI-powered solution designed to enhance operational efficiency and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to delive', '/storage/projects/2_production-ai-powered-metal-walk-through-monitoring-cctv.png', '/projects/production-ai-powered-metal-walk-through-monitoring-cctv', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Production Ai-Powered Metal Walk-Through Monitoring Cctv\",\"description\":\"An advanced AI-powered solution designed to enhance operational efficiency and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to deliver real-time insights and automated decision-making capabilities. The system is engineered to handle complex detection and inspection tasks with high accuracy, reducing manual intervention and improving overall productivity. By integrating state-of-the-art AI models, this solution provides intelligent automation that adapts to various operational scenarios, making it an invaluable tool for modern m\",\"applicationCategory\":\"AI & Machine Learning\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Production Ai-Powered Metal Walk-Through Monitoring Cctv\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'production-ai-powered-metal-walk-through-monitoring-cctv', 'Transform your metalworking operations with cutting-edge AI-powered visual inspection. Our intelligent system detects defects with precision, reduces manual inspection time by 90%, and ensures consistent quality across your production line.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/2_production-ai-powered-metal-walk-through-monitoring-cctv.png', NULL, 'AI & Machine Learning', NULL, 'completed', NULL, '[\"Computer Vision\",\"Quality Control\",\"Real-time Monitoring\",\"Sensors\",\"Production\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 1, '2025-11-01 01:40:01', '2025-11-01 04:37:10', NULL, NULL),
(2, 'Ai Gift Box Counting', 'Ai Gift Box Counting - AI Solution', 'An advanced AI-powered solution designed to enhance operational efficiency and quality control.', 'Web, Computer Vision, Quality Control, AI & Machine Learning, SATNUSA, Industrial Solutions', 'Ai Gift Box Counting | SATNUSA', 'An advanced AI-powered solution designed to enhance operational efficiency and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to delive', '/storage/projects/3_ai-gift-box-counting.png', '/projects/ai-gift-box-counting', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Ai Gift Box Counting\",\"description\":\"An advanced AI-powered solution designed to enhance operational efficiency and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to deliver real-time insights and automated decision-making capabilities. The system is engineered to handle complex detection and inspection tasks with high accuracy, reducing manual intervention and improving overall productivity. By integrating state-of-the-art AI models, this solution provides intelligent automation that adapts to various operational scenarios, making it an invaluable tool for modern m\",\"applicationCategory\":\"AI & Machine Learning\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Ai Gift Box Counting\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'ai-gift-box-counting', 'Revolutionize quality control with advanced computer vision technology. This AI-powered solution automatically identifies defects, streamlines inspection workflows, and delivers real-time insights to maintain the highest quality standards.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/3_ai-gift-box-counting.png', NULL, 'AI & Machine Learning', NULL, 'completed', NULL, '[\"Web\",\"Computer Vision\",\"Quality Control\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 2, '2025-11-01 01:40:01', '2025-11-01 04:37:10', NULL, NULL),
(3, 'Ai Keyboard Sticker Notebook Inspection', 'Ai Keyboard Sticker Notebook Inspection - AI Solution', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control.', 'Web, Computer Vision, Quality Control, AI & Machine Learning, SATNUSA, Industrial Solutions', 'Ai Keyboard Sticker Notebook Inspection | SATNUSA', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision tec', '/storage/projects/4_ai-keyboard-sticker-notebook-inspection.png', '/projects/ai-keyboard-sticker-notebook-inspection', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Ai Keyboard Sticker Notebook Inspection\",\"description\":\"An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to deliver real-time insights and automated decision-making capabilities. The system is engineered to handle complex detection and inspection tasks with high accuracy, reducing manual intervention and improving overall productivity. By integrating state-of-the-art AI models, this solution provides intelligent automation that adapts to various operational scenarios, making it an invaluable \",\"applicationCategory\":\"AI & Machine Learning\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Ai Keyboard Sticker Notebook Inspection\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'ai-keyboard-sticker-notebook-inspection', 'Transform your electronics manufacturing operations with cutting-edge AI-powered visual inspection. Our intelligent system detects defects with precision, reduces manual inspection time by 90%, and ensures consistent quality across your production line.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/4_ai-keyboard-sticker-notebook-inspection.png', NULL, 'AI & Machine Learning', NULL, 'completed', NULL, '[\"Web\",\"Computer Vision\",\"Quality Control\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 3, '2025-11-01 01:40:01', '2025-11-01 04:37:10', NULL, NULL),
(4, 'Ai Pcb Router Cutting Inspection', 'Ai Pcb Router Cutting Inspection - AI Solution', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control.', 'Computer Vision, Quality Control, AI & Machine Learning, SATNUSA, Industrial Solutions', 'Ai Pcb Router Cutting Inspection | SATNUSA', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision tec', '/storage/projects/5_ai-pcb-router-cutting-inspection.png', '/projects/ai-pcb-router-cutting-inspection', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Ai Pcb Router Cutting Inspection\",\"description\":\"An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to deliver real-time insights and automated decision-making capabilities. The system is engineered to handle complex detection and inspection tasks with high accuracy, reducing manual intervention and improving overall productivity. By integrating state-of-the-art AI models, this solution provides intelligent automation that adapts to various operational scenarios, making it an invaluable \",\"applicationCategory\":\"AI & Machine Learning\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Ai Pcb Router Cutting Inspection\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'ai-pcb-router-cutting-inspection', 'Eliminate production bottlenecks with intelligent visual inspection. This state-of-the-art AI system combines speed, accuracy, and reliability to ensure every product meets your exact specifications before it reaches customers.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/5_ai-pcb-router-cutting-inspection.png', NULL, 'AI & Machine Learning', NULL, 'completed', NULL, '[\"Computer Vision\",\"Quality Control\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 4, '2025-11-01 01:40:02', '2025-11-01 04:37:10', NULL, NULL),
(5, 'Ai Placement Shielding Inspection', 'Ai Placement Shielding Inspection - AI Solution', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control.', 'Web, Computer Vision, Quality Control, AI & Machine Learning, SATNUSA, Industrial Solutions', 'Ai Placement Shielding Inspection | SATNUSA', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision tec', '/storage/projects/6_ai-placement-shielding-inspection.png', '/projects/ai-placement-shielding-inspection', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Ai Placement Shielding Inspection\",\"description\":\"An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to deliver real-time insights and automated decision-making capabilities. The system is engineered to handle complex detection and inspection tasks with high accuracy, reducing manual intervention and improving overall productivity. By integrating state-of-the-art AI models, this solution provides intelligent automation that adapts to various operational scenarios, making it an invaluable \",\"applicationCategory\":\"AI & Machine Learning\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Ai Placement Shielding Inspection\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'ai-placement-shielding-inspection', 'Experience next-generation automated inspection powered by deep learning algorithms. Our system processes thousands of images per hour, catches defects human eyes might miss, and provides detailed analytics for continuous improvement.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/6_ai-placement-shielding-inspection.png', NULL, 'AI & Machine Learning', NULL, 'completed', NULL, '[\"Web\",\"Computer Vision\",\"Quality Control\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 5, '2025-11-01 01:40:02', '2025-11-01 04:37:10', NULL, NULL),
(6, 'Ai Auto Solder Inspection Machine', 'Ai Auto Solder Inspection Machine - AI Solution', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control.', 'Computer Vision, Quality Control, AI & Machine Learning, SATNUSA, Industrial Solutions', 'Ai Auto Solder Inspection Machine | SATNUSA', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision tec', '/storage/projects/7_ai-auto-solder-inspection-machine.png', '/projects/ai-auto-solder-inspection-machine', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Ai Auto Solder Inspection Machine\",\"description\":\"An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to deliver real-time insights and automated decision-making capabilities. The system is engineered to handle complex detection and inspection tasks with high accuracy, reducing manual intervention and improving overall productivity. By integrating state-of-the-art AI models, this solution provides intelligent automation that adapts to various operational scenarios, making it an invaluable \",\"applicationCategory\":\"AI & Machine Learning\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Ai Auto Solder Inspection Machine\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'ai-auto-solder-inspection-machine', 'Eliminate production bottlenecks with intelligent visual inspection. This state-of-the-art AI system combines speed, accuracy, and reliability to ensure every product meets your exact specifications before it reaches customers.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/7_ai-auto-solder-inspection-machine.png', NULL, 'AI & Machine Learning', NULL, 'completed', NULL, '[\"Computer Vision\",\"Quality Control\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 6, '2025-11-01 01:40:02', '2025-11-01 04:37:10', NULL, NULL),
(7, 'Ai Smartphone Inside Inspection', 'Ai Smartphone Inside Inspection - AI Solution', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control.', 'Computer Vision, Quality Control, AI & Machine Learning, SATNUSA, Industrial Solutions', 'Ai Smartphone Inside Inspection | SATNUSA', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision tec', '/storage/projects/8_ai-smartphone-inside-inspection.png', '/projects/ai-smartphone-inside-inspection', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Ai Smartphone Inside Inspection\",\"description\":\"An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to deliver real-time insights and automated decision-making capabilities. The system is engineered to handle complex detection and inspection tasks with high accuracy, reducing manual intervention and improving overall productivity. By integrating state-of-the-art AI models, this solution provides intelligent automation that adapts to various operational scenarios, making it an invaluable \",\"applicationCategory\":\"AI & Machine Learning\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Ai Smartphone Inside Inspection\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'ai-smartphone-inside-inspection', 'Transform your mobile device operations with cutting-edge AI-powered visual inspection. Our intelligent system detects defects with precision, reduces manual inspection time by 90%, and ensures consistent quality across your production line.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/8_ai-smartphone-inside-inspection.png', NULL, 'AI & Machine Learning', NULL, 'completed', NULL, '[\"Computer Vision\",\"Quality Control\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 7, '2025-11-01 01:40:02', '2025-11-01 04:37:10', NULL, NULL),
(8, 'Ai Usb Cover Inspection', 'Ai Usb Cover Inspection - AI Solution', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control.', 'Computer Vision, Quality Control, AI & Machine Learning, SATNUSA, Industrial Solutions', 'Ai Usb Cover Inspection | SATNUSA', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision tec', '/storage/projects/9_ai-usb-cover-inspection.png', '/projects/ai-usb-cover-inspection', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Ai Usb Cover Inspection\",\"description\":\"An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to deliver real-time insights and automated decision-making capabilities. The system is engineered to handle complex detection and inspection tasks with high accuracy, reducing manual intervention and improving overall productivity. By integrating state-of-the-art AI models, this solution provides intelligent automation that adapts to various operational scenarios, making it an invaluable \",\"applicationCategory\":\"AI & Machine Learning\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Ai Usb Cover Inspection\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'ai-usb-cover-inspection', 'Experience next-generation automated inspection powered by deep learning algorithms. Our system processes thousands of images per hour, catches defects human eyes might miss, and provides detailed analytics for continuous improvement.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/9_ai-usb-cover-inspection.png', NULL, 'AI & Machine Learning', NULL, 'completed', NULL, '[\"Computer Vision\",\"Quality Control\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 8, '2025-11-01 01:40:02', '2025-11-01 04:37:10', NULL, NULL),
(9, 'Ai Inside Notebook Inspection', 'Ai Inside Notebook Inspection - AI Solution', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control.', 'Computer Vision, Quality Control, AI & Machine Learning, SATNUSA, Industrial Solutions', 'Ai Inside Notebook Inspection | SATNUSA', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision tec', '/storage/projects/10_ai-inside-notebook-inspection.png', '/projects/ai-inside-notebook-inspection', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Ai Inside Notebook Inspection\",\"description\":\"An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to deliver real-time insights and automated decision-making capabilities. The system is engineered to handle complex detection and inspection tasks with high accuracy, reducing manual intervention and improving overall productivity. By integrating state-of-the-art AI models, this solution provides intelligent automation that adapts to various operational scenarios, making it an invaluable \",\"applicationCategory\":\"AI & Machine Learning\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Ai Inside Notebook Inspection\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'ai-inside-notebook-inspection', 'Transform your electronics manufacturing operations with cutting-edge AI-powered visual inspection. Our intelligent system detects defects with precision, reduces manual inspection time by 90%, and ensures consistent quality across your production line.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/10_ai-inside-notebook-inspection.png', NULL, 'AI & Machine Learning', NULL, 'completed', NULL, '[\"Computer Vision\",\"Quality Control\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 9, '2025-11-01 01:40:02', '2025-11-01 04:37:10', NULL, NULL),
(10, 'Ai Notebook Balancing Inspection', 'Ai Notebook Balancing Inspection - AI Solution', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control.', 'Web, Computer Vision, Quality Control, AI & Machine Learning, SATNUSA, Industrial Solutions', 'Ai Notebook Balancing Inspection | SATNUSA', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision tec', '/storage/projects/11_ai-notebook-balancing-inspection.png', '/projects/ai-notebook-balancing-inspection', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Ai Notebook Balancing Inspection\",\"description\":\"An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to deliver real-time insights and automated decision-making capabilities. The system is engineered to handle complex detection and inspection tasks with high accuracy, reducing manual intervention and improving overall productivity. By integrating state-of-the-art AI models, this solution provides intelligent automation that adapts to various operational scenarios, making it an invaluable \",\"applicationCategory\":\"AI & Machine Learning\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Ai Notebook Balancing Inspection\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'ai-notebook-balancing-inspection', 'Eliminate production bottlenecks with intelligent visual inspection. This state-of-the-art AI system combines speed, accuracy, and reliability to ensure every product meets your exact specifications before it reaches customers.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/11_ai-notebook-balancing-inspection.png', NULL, 'AI & Machine Learning', NULL, 'completed', NULL, '[\"Web\",\"Computer Vision\",\"Quality Control\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 10, '2025-11-01 01:40:03', '2025-11-01 04:37:10', NULL, NULL),
(11, 'Ai Aoi Monitor Label Inspection', 'Ai Aoi Monitor Label Inspection - AI Solution', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control.', 'Web, Computer Vision, Quality Control, AI & Machine Learning, SATNUSA, Industrial Solutions', 'Ai Aoi Monitor Label Inspection | SATNUSA', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision tec', '/storage/projects/12_ai-aoi-monitor-label-inspection.png', '/projects/ai-aoi-monitor-label-inspection', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Ai Aoi Monitor Label Inspection\",\"description\":\"An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to deliver real-time insights and automated decision-making capabilities. The system is engineered to handle complex detection and inspection tasks with high accuracy, reducing manual intervention and improving overall productivity. By integrating state-of-the-art AI models, this solution provides intelligent automation that adapts to various operational scenarios, making it an invaluable \",\"applicationCategory\":\"AI & Machine Learning\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Ai Aoi Monitor Label Inspection\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'ai-aoi-monitor-label-inspection', 'Revolutionize quality control with advanced computer vision technology. This AI-powered solution automatically identifies defects, streamlines inspection workflows, and delivers real-time insights to maintain the highest quality standards.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/12_ai-aoi-monitor-label-inspection.png', NULL, 'AI & Machine Learning', NULL, 'completed', NULL, '[\"Web\",\"Computer Vision\",\"Quality Control\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 11, '2025-11-01 01:40:03', '2025-11-01 04:37:10', NULL, NULL),
(12, 'Ai Digitag Color Inspection System', 'Ai Digitag Color Inspection System - AI Solution', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control.', 'Computer Vision, Quality Control, AI & Machine Learning, SATNUSA, Industrial Solutions', 'Ai Digitag Color Inspection System | SATNUSA', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision tec', '/storage/projects/13_ai-digitag-color-inspection-system.png', '/projects/ai-digitag-color-inspection-system', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Ai Digitag Color Inspection System\",\"description\":\"An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to deliver real-time insights and automated decision-making capabilities. The system is engineered to handle complex detection and inspection tasks with high accuracy, reducing manual intervention and improving overall productivity. By integrating state-of-the-art AI models, this solution provides intelligent automation that adapts to various operational scenarios, making it an invaluable \",\"applicationCategory\":\"AI & Machine Learning\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Ai Digitag Color Inspection System\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'ai-digitag-color-inspection-system', 'Eliminate production bottlenecks with intelligent visual inspection. This state-of-the-art AI system combines speed, accuracy, and reliability to ensure every product meets your exact specifications before it reaches customers.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/13_ai-digitag-color-inspection-system.png', NULL, 'AI & Machine Learning', NULL, 'completed', NULL, '[\"Computer Vision\",\"Quality Control\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 12, '2025-11-01 01:40:03', '2025-11-01 04:37:10', NULL, NULL),
(13, 'Tablet Ai Voice Inspection Quality Check', 'Tablet Ai Voice Inspection Quality Check - AI Solution', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control.', 'Web, Computer Vision, Quality Control, AI & Machine Learning, SATNUSA, Industrial Solutions', 'Tablet Ai Voice Inspection Quality Check | SATNUSA', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision tec', '/storage/projects/14_tablet-ai-voice-inspection-quality-check.png', '/projects/tablet-ai-voice-inspection-quality-check', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Tablet Ai Voice Inspection Quality Check\",\"description\":\"An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to deliver real-time insights and automated decision-making capabilities. The system is engineered to handle complex detection and inspection tasks with high accuracy, reducing manual intervention and improving overall productivity. By integrating state-of-the-art AI models, this solution provides intelligent automation that adapts to various operational scenarios, making it an invaluable \",\"applicationCategory\":\"AI & Machine Learning\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Tablet Ai Voice Inspection Quality Check\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'tablet-ai-voice-inspection-quality-check', 'Eliminate production bottlenecks with intelligent visual inspection. This state-of-the-art AI system combines speed, accuracy, and reliability to ensure every product meets your exact specifications before it reaches customers.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/14_tablet-ai-voice-inspection-quality-check.png', NULL, 'AI & Machine Learning', NULL, 'completed', NULL, '[\"Web\",\"Computer Vision\",\"Quality Control\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 13, '2025-11-01 01:40:03', '2025-11-01 04:37:10', NULL, NULL),
(14, 'Iot System To Prevent Incorrect Component Selection By Operator', 'Iot System To Prevent Incorrect Component Selection By Opera', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web, IoT, Real-time Monitoring, Sensors, Web Development, SATNUSA, Industrial Solutions', 'Iot System To Prevent Incorrect Component Selec... | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/15_iot-system-to-prevent-incorrect-component-selection-by-operator.png', '/projects/iot-system-to-prevent-incorrect-component-selection-by-operator', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Iot System To Prevent Incorrect Component Selection By Operator\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Iot System To Prevent Incorrect Component Selection By Operator\n\nThis platforms project implements Web, IoT to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\",\"IoT\"]}', 80, 1, 'iot-system-to-prevent-incorrect-component-selection-by-operator', 'A comprehensive digital solution designed to streamline operations and enhance user experience. Built with modern technologies and best practices, this platform delivers robust functionality, intuitive interface, and scalable architecture.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/15_iot-system-to-prevent-incorrect-component-selection-by-operator.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Web\",\"IoT\",\"Real-time Monitoring\",\"Sensors\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 14, '2025-11-01 01:40:03', '2025-11-01 04:37:10', NULL, NULL),
(15, 'Usb Life Cycle Monitoring', 'Usb Life Cycle Monitoring - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web, Real-time Monitoring, Sensors, Web Development, SATNUSA, Industrial Solutions', 'Usb Life Cycle Monitoring | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/16_usb-life-cycle-monitoring.png', '/projects/usb-life-cycle-monitoring', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Usb Life Cycle Monitoring\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Usb Life Cycle Monitoring\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'usb-life-cycle-monitoring', 'Protect your assets with intelligent monitoring and real-time threat detection. This advanced security system leverages AI to identify anomalies, track activities, and provide instant alerts, ensuring round-the-clock protection.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/16_usb-life-cycle-monitoring.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Web\",\"Real-time Monitoring\",\"Sensors\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 15, '2025-11-01 01:40:03', '2025-11-01 04:37:10', NULL, NULL),
(16, 'Production Operator Attendance', 'Production Operator Attendance - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web, Production, Attendance, Web Development, SATNUSA, Industrial Solutions', 'Production Operator Attendance | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/17_production-operator-attendance.png', '/projects/production-operator-attendance', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Production Operator Attendance\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Production Operator Attendance\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'production-operator-attendance', 'An innovative solution combining modern technology with practical functionality. This project demonstrates expertise in problem-solving, user-centric design, and delivering measurable results that drive business success.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/17_production-operator-attendance.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Web\",\"Production\",\"Attendance\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 16, '2025-11-01 01:40:03', '2025-11-01 04:37:10', NULL, NULL),
(17, 'Ticketing Order Fabrication', 'Ticketing Order Fabrication - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web, Web Development, SATNUSA, Industrial Solutions', 'Ticketing Order Fabrication | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/18_ticketing-order-fabrication.png', '/projects/ticketing-order-fabrication', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Ticketing Order Fabrication\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Ticketing Order Fabrication\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'ticketing-order-fabrication', 'An innovative solution combining modern technology with practical functionality. This project demonstrates expertise in problem-solving, user-centric design, and delivering measurable results that drive business success.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/18_ticketing-order-fabrication.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Web\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 17, '2025-11-01 01:40:03', '2025-11-01 04:37:10', NULL, NULL);
INSERT INTO `projects` (`id`, `title`, `meta_title`, `meta_description`, `meta_keywords`, `og_title`, `og_description`, `og_image`, `canonical_url`, `schema_markup`, `ai_summary`, `tech_stack_details`, `seo_score`, `index_follow`, `slug`, `description`, `impact_statement`, `content`, `context`, `role`, `problem`, `solution`, `integration`, `result`, `image`, `images`, `category`, `domain`, `status`, `technologies`, `tags`, `client`, `url`, `github_url`, `completed_at`, `start_date`, `end_date`, `featured`, `published`, `is_active`, `sort_order`, `created_at`, `updated_at`, `deleted_at`, `related_project_ids`) VALUES
(18, 'Ai Smart Parking And Mobile Apps Enabled Availability Check', 'Ai Smart Parking And Mobile Apps Enabled Availability Check', 'An advanced AI-powered solution designed to enhance operational efficiency and quality control.', 'Mobile, Computer Vision, Quality Control, Mobile Application, AI & Machine Learning, SATNUSA, Industrial Solutions', 'Ai Smart Parking And Mobile Apps Enabled Availa... | SATNUSA', 'An advanced AI-powered solution designed to enhance operational efficiency and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to delive', '/storage/projects/19_ai-smart-parking-and-mobile-apps-enabled-availability-check.png', '/projects/ai-smart-parking-and-mobile-apps-enabled-availability-check', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Ai Smart Parking And Mobile Apps Enabled Availability Check\",\"description\":\"An advanced AI-powered solution designed to enhance operational efficiency and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to deliver real-time insights and automated decision-making capabilities. The system is engineered to handle complex detection and inspection tasks with high accuracy, reducing manual intervention and improving overall productivity. By integrating state-of-the-art AI models, this solution provides intelligent automation that adapts to various operational scenarios, making it an invaluable tool for modern m\",\"applicationCategory\":\"AI & Machine Learning\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Ai Smart Parking And Mobile Apps Enabled Availability Check\n\nThis platforms project implements Mobile to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Mobile\"]}', 80, 1, 'ai-smart-parking-and-mobile-apps-enabled-availability-check', 'Eliminate production bottlenecks with intelligent visual inspection. This state-of-the-art AI system combines speed, accuracy, and reliability to ensure every product meets your exact specifications before it reaches customers.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/19_ai-smart-parking-and-mobile-apps-enabled-availability-check.png', NULL, 'AI & Machine Learning', NULL, 'completed', NULL, '[\"Mobile\",\"Computer Vision\",\"Quality Control\",\"Mobile Application\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 18, '2025-11-01 01:40:03', '2025-11-01 04:37:11', NULL, NULL),
(19, 'Mysatnusa Phone Extension', 'Mysatnusa Phone Extension - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web Development, SATNUSA, Industrial Solutions', 'Mysatnusa Phone Extension | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/20_mysatnusa-phone-extension.png', '/projects/mysatnusa-phone-extension', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Mysatnusa Phone Extension\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Mysatnusa Phone Extension\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'mysatnusa-phone-extension', 'An innovative solution combining modern technology with practical functionality. This project demonstrates expertise in problem-solving, user-centric design, and delivering measurable results that drive business success.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/20_mysatnusa-phone-extension.png', NULL, 'Web Development', NULL, 'completed', NULL, '[]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 19, '2025-11-01 01:40:03', '2025-11-01 04:37:11', NULL, NULL),
(20, 'Employee Attendance System', 'Employee Attendance System - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web, Attendance, Web Development, SATNUSA, Industrial Solutions', 'Employee Attendance System | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/21_employee-attendance-system.png', '/projects/employee-attendance-system', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Employee Attendance System\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Employee Attendance System\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'employee-attendance-system', 'A comprehensive digital solution designed to streamline operations and enhance user experience. Built with modern technologies and best practices, this platform delivers robust functionality, intuitive interface, and scalable architecture.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/21_employee-attendance-system.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Web\",\"Attendance\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 20, '2025-11-01 01:40:04', '2025-11-01 04:37:11', NULL, NULL),
(21, 'New Satnusa Website', 'New Satnusa Website - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web, Web Development, SATNUSA, Industrial Solutions', 'New Satnusa Website | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/22_new-satnusa-website.png', '/projects/new-satnusa-website', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"New Satnusa Website\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: New Satnusa Website\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'new-satnusa-website', 'Experience digital transformation with a feature-rich application that puts users first. This solution integrates seamlessly with existing systems, automates complex workflows, and provides actionable insights through intuitive dashboards.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/22_new-satnusa-website.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Web\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 21, '2025-11-01 01:40:04', '2025-11-01 04:37:11', NULL, NULL),
(22, 'Daily Submit Achievement Mobile Apps Web', 'Daily Submit Achievement Mobile Apps Web - Mobile App', 'A user-friendly mobile application designed to provide on-the-go access to essential business functions.', 'Web, Mobile, Computer Vision, Quality Control, Mobile Application, Mobile Apps, SATNUSA, Industrial Solutions', 'Daily Submit Achievement Mobile Apps Web | SATNUSA', 'A user-friendly mobile application designed to provide on-the-go access to essential business functions. This solution combines intuitive design with powerful functionality, enabling users to perform ', '/storage/projects/23_daily-submit-achievement-mobile-apps-web.png', '/projects/daily-submit-achievement-mobile-apps-web', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Daily Submit Achievement Mobile Apps Web\",\"description\":\"A user-friendly mobile application designed to provide on-the-go access to essential business functions. This solution combines intuitive design with powerful functionality, enabling users to perform critical tasks from anywhere, at any time. The app features offline capabilities, real-time synchronization, and seamless integration with existing systems. Built with cross-platform compatibility in mind, it delivers a consistent experience across different devices while maintaining high performance and security standards. This mobile solution empowers teams with instant access to information and\",\"applicationCategory\":\"Mobile Apps\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Daily Submit Achievement Mobile Apps Web\n\nThis platforms project implements Web, Mobile to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\",\"Mobile\"]}', 80, 1, 'daily-submit-achievement-mobile-apps-web', 'Experience next-generation automated inspection powered by deep learning algorithms. Our system processes thousands of images per hour, catches defects human eyes might miss, and provides detailed analytics for continuous improvement.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/23_daily-submit-achievement-mobile-apps-web.png', NULL, 'Mobile Apps', NULL, 'completed', NULL, '[\"Web\",\"Mobile\",\"Computer Vision\",\"Quality Control\",\"Mobile Application\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 22, '2025-11-01 01:40:04', '2025-11-01 04:37:11', NULL, NULL),
(23, 'Meeting Room Monitoring', 'Meeting Room Monitoring - IoT Platform', 'A sophisticated IoT solution that connects physical devices with digital intelligence to enable smart monitoring and control.', 'Real-time Monitoring, Sensors, IoT, SATNUSA, Industrial Solutions', 'Meeting Room Monitoring | SATNUSA', 'A sophisticated IoT solution that connects physical devices with digital intelligence to enable smart monitoring and control. This project implements sensor networks, real-time data collection, and au', '/storage/projects/24_meeting-room-monitoring.png', '/projects/meeting-room-monitoring', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Meeting Room Monitoring\",\"description\":\"A sophisticated IoT solution that connects physical devices with digital intelligence to enable smart monitoring and control. This project implements sensor networks, real-time data collection, and automated response systems to optimize operational workflows. The platform provides comprehensive visibility into equipment status, environmental conditions, and system performance, allowing for proactive maintenance and immediate issue resolution. With its robust architecture and scalable design, this IoT system transforms traditional operations into a connected, data-driven ecosystem that enhances\",\"applicationCategory\":\"IoT\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Meeting Room Monitoring\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 90, 1, 'meeting-room-monitoring', 'Enhance safety and security with smart surveillance technology. Our system combines high-definition monitoring, intelligent analytics, and automated alerts to create a comprehensive security ecosystem.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/24_meeting-room-monitoring.png', NULL, 'IoT', NULL, 'completed', NULL, '[\"Real-time Monitoring\",\"Sensors\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 23, '2025-11-01 01:40:04', '2025-11-01 04:37:11', NULL, NULL),
(24, 'Cleaning Service Monitoring', 'Cleaning Service Monitoring - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web, Real-time Monitoring, Sensors, Web Development, SATNUSA, Industrial Solutions', 'Cleaning Service Monitoring | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/25_cleaning-service-monitoring.png', '/projects/cleaning-service-monitoring', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Cleaning Service Monitoring\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Cleaning Service Monitoring\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'cleaning-service-monitoring', 'Protect your assets with intelligent monitoring and real-time threat detection. This advanced security system leverages AI to identify anomalies, track activities, and provide instant alerts, ensuring round-the-clock protection.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/25_cleaning-service-monitoring.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Web\",\"Real-time Monitoring\",\"Sensors\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 24, '2025-11-01 01:40:04', '2025-11-01 04:37:11', NULL, NULL),
(25, 'Mysatnusa Plant Maintenance', 'Mysatnusa Plant Maintenance - AI Solution', 'An advanced AI-powered solution designed to enhance operational efficiency and quality control.', 'Computer Vision, Quality Control, Maintenance, AI & Machine Learning, SATNUSA, Industrial Solutions', 'Mysatnusa Plant Maintenance | SATNUSA', 'An advanced AI-powered solution designed to enhance operational efficiency and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to delive', '/storage/projects/26_mysatnusa-plant-maintenance.png', '/projects/mysatnusa-plant-maintenance', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Mysatnusa Plant Maintenance\",\"description\":\"An advanced AI-powered solution designed to enhance operational efficiency and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to deliver real-time insights and automated decision-making capabilities. The system is engineered to handle complex detection and inspection tasks with high accuracy, reducing manual intervention and improving overall productivity. By integrating state-of-the-art AI models, this solution provides intelligent automation that adapts to various operational scenarios, making it an invaluable tool for modern m\",\"applicationCategory\":\"AI & Machine Learning\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Mysatnusa Plant Maintenance\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'mysatnusa-plant-maintenance', 'Transform your manufacturing operations with cutting-edge AI-powered visual inspection. Our intelligent system detects defects with precision, reduces manual inspection time by 90%, and ensures consistent quality across your production line.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/26_mysatnusa-plant-maintenance.png', NULL, 'AI & Machine Learning', NULL, 'completed', NULL, '[\"Computer Vision\",\"Quality Control\",\"Maintenance\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 25, '2025-11-01 01:40:04', '2025-11-01 04:37:11', NULL, NULL),
(26, 'Engineering Change Notice', 'Engineering Change Notice - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web Development, SATNUSA, Industrial Solutions', 'Engineering Change Notice | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/27_engineering-change-notice.png', '/projects/engineering-change-notice', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Engineering Change Notice\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Engineering Change Notice\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'engineering-change-notice', 'An innovative solution combining modern technology with practical functionality. This project demonstrates expertise in problem-solving, user-centric design, and delivering measurable results that drive business success.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/27_engineering-change-notice.png', NULL, 'Web Development', NULL, 'completed', NULL, '[]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 26, '2025-11-01 01:40:04', '2025-11-01 04:37:11', NULL, NULL),
(27, 'Agile Methodology For Project Management', 'Agile Methodology For Project Management - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web Development, SATNUSA, Industrial Solutions', 'Agile Methodology For Project Management | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/28_agile-methodology-for-project-management.png', '/projects/agile-methodology-for-project-management', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Agile Methodology For Project Management\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Agile Methodology For Project Management\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'agile-methodology-for-project-management', 'An innovative solution combining modern technology with practical functionality. This project demonstrates expertise in problem-solving, user-centric design, and delivering measurable results that drive business success.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/28_agile-methodology-for-project-management.png', NULL, 'Web Development', NULL, 'completed', NULL, '[]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 27, '2025-11-01 01:40:04', '2025-11-01 04:37:11', NULL, NULL),
(28, 'Satnusa Pdf Editor', 'Satnusa Pdf Editor - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web, Web Development, SATNUSA, Industrial Solutions', 'Satnusa Pdf Editor | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/29_satnusa-pdf-editor.png', '/projects/satnusa-pdf-editor', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Satnusa Pdf Editor\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Satnusa Pdf Editor\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'satnusa-pdf-editor', 'An innovative solution combining modern technology with practical functionality. This project demonstrates expertise in problem-solving, user-centric design, and delivering measurable results that drive business success.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/29_satnusa-pdf-editor.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Web\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 28, '2025-11-01 01:40:04', '2025-11-01 04:37:11', NULL, NULL),
(29, 'Visual Inspection For Spring Sheet Metal', 'Visual Inspection For Spring Sheet Metal - AI Solution', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control.', 'Computer Vision, Quality Control, AI & Machine Learning, SATNUSA, Industrial Solutions', 'Visual Inspection For Spring Sheet Metal | SATNUSA', 'An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision tec', '/storage/projects/30_visual-inspection-for-spring-sheet-metal.png', '/projects/visual-inspection-for-spring-sheet-metal', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Visual Inspection For Spring Sheet Metal\",\"description\":\"An advanced AI-powered solution designed to enhance quality inspection and defect detection and quality control. This project leverages cutting-edge machine learning algorithms and computer vision technology to deliver real-time insights and automated decision-making capabilities. The system is engineered to handle complex detection and inspection tasks with high accuracy, reducing manual intervention and improving overall productivity. By integrating state-of-the-art AI models, this solution provides intelligent automation that adapts to various operational scenarios, making it an invaluable \",\"applicationCategory\":\"AI & Machine Learning\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Visual Inspection For Spring Sheet Metal\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'visual-inspection-for-spring-sheet-metal', 'Eliminate production bottlenecks with intelligent visual inspection. This state-of-the-art AI system combines speed, accuracy, and reliability to ensure every product meets your exact specifications before it reaches customers.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/30_visual-inspection-for-spring-sheet-metal.png', NULL, 'AI & Machine Learning', NULL, 'completed', NULL, '[\"Computer Vision\",\"Quality Control\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 29, '2025-11-01 01:40:05', '2025-11-01 04:37:11', NULL, NULL),
(30, 'Digital Twin For Moulding', 'Digital Twin For Moulding - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web Development, SATNUSA, Industrial Solutions', 'Digital Twin For Moulding | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/31_digital-twin-for-moulding.png', '/projects/digital-twin-for-moulding', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Digital Twin For Moulding\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Digital Twin For Moulding\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'digital-twin-for-moulding', 'An innovative solution combining modern technology with practical functionality. This project demonstrates expertise in problem-solving, user-centric design, and delivering measurable results that drive business success.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/31_digital-twin-for-moulding.png', NULL, 'Web Development', NULL, 'completed', NULL, '[]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 30, '2025-11-01 01:40:05', '2025-11-01 04:37:11', NULL, NULL),
(31, 'Tooling Smart Rack', 'Tooling Smart Rack - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web Development, SATNUSA, Industrial Solutions', 'Tooling Smart Rack | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/32_tooling-smart-rack.png', '/projects/tooling-smart-rack', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Tooling Smart Rack\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Tooling Smart Rack\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'tooling-smart-rack', 'An innovative solution combining modern technology with practical functionality. This project demonstrates expertise in problem-solving, user-centric design, and delivering measurable results that drive business success.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/32_tooling-smart-rack.png', NULL, 'Web Development', NULL, 'completed', NULL, '[]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 31, '2025-11-01 01:40:05', '2025-11-01 04:37:11', NULL, NULL),
(32, 'Moulding Stamping Mes System', 'Moulding Stamping Mes System - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web Development, SATNUSA, Industrial Solutions', 'Moulding Stamping Mes System | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/33_moulding-stamping-mes-system.png', '/projects/moulding-stamping-mes-system', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Moulding Stamping Mes System\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Moulding Stamping Mes System\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'moulding-stamping-mes-system', 'Experience digital transformation with a feature-rich application that puts users first. This solution integrates seamlessly with existing systems, automates complex workflows, and provides actionable insights through intuitive dashboards.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/33_moulding-stamping-mes-system.png', NULL, 'Web Development', NULL, 'completed', NULL, '[]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 32, '2025-11-01 01:40:05', '2025-11-01 04:37:11', NULL, NULL),
(33, 'Digital Sop Mysatnusa Platform', 'Digital Sop Mysatnusa Platform - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web, Web Development, SATNUSA, Industrial Solutions', 'Digital Sop Mysatnusa Platform | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/34_digital-sop-mysatnusa-platform.png', '/projects/digital-sop-mysatnusa-platform', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Digital Sop Mysatnusa Platform\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Digital Sop Mysatnusa Platform\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'digital-sop-mysatnusa-platform', 'An innovative solution combining modern technology with practical functionality. This project demonstrates expertise in problem-solving, user-centric design, and delivering measurable results that drive business success.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/34_digital-sop-mysatnusa-platform.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Web\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 33, '2025-11-01 01:40:05', '2025-11-01 04:37:11', NULL, NULL),
(34, 'Auto Counting Sme Machine Output', 'Auto Counting Sme Machine Output - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web, Web Development, SATNUSA, Industrial Solutions', 'Auto Counting Sme Machine Output | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/35_auto-counting-sme-machine-output.png', '/projects/auto-counting-sme-machine-output', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Auto Counting Sme Machine Output\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Auto Counting Sme Machine Output\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'auto-counting-sme-machine-output', 'An innovative solution combining modern technology with practical functionality. This project demonstrates expertise in problem-solving, user-centric design, and delivering measurable results that drive business success.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/35_auto-counting-sme-machine-output.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Web\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 34, '2025-11-01 01:40:05', '2025-11-01 04:37:11', NULL, NULL),
(35, 'Moulding Status Machine', 'Moulding Status Machine - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web, Web Development, SATNUSA, Industrial Solutions', 'Moulding Status Machine | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/36_moulding-status-machine.png', '/projects/moulding-status-machine', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Moulding Status Machine\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Moulding Status Machine\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'moulding-status-machine', 'An innovative solution combining modern technology with practical functionality. This project demonstrates expertise in problem-solving, user-centric design, and delivering measurable results that drive business success.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/36_moulding-status-machine.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Web\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 35, '2025-11-01 01:40:05', '2025-11-01 04:37:11', NULL, NULL);
INSERT INTO `projects` (`id`, `title`, `meta_title`, `meta_description`, `meta_keywords`, `og_title`, `og_description`, `og_image`, `canonical_url`, `schema_markup`, `ai_summary`, `tech_stack_details`, `seo_score`, `index_follow`, `slug`, `description`, `impact_statement`, `content`, `context`, `role`, `problem`, `solution`, `integration`, `result`, `image`, `images`, `category`, `domain`, `status`, `technologies`, `tags`, `client`, `url`, `github_url`, `completed_at`, `start_date`, `end_date`, `featured`, `published`, `is_active`, `sort_order`, `created_at`, `updated_at`, `deleted_at`, `related_project_ids`) VALUES
(36, 'Smart Gate System', 'Smart Gate System - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web, Web Development, SATNUSA, Industrial Solutions', 'Smart Gate System | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/37_smart-gate-system.png', '/projects/smart-gate-system', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Smart Gate System\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Smart Gate System\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'smart-gate-system', 'A comprehensive digital solution designed to streamline operations and enhance user experience. Built with modern technologies and best practices, this platform delivers robust functionality, intuitive interface, and scalable architecture.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/37_smart-gate-system.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Web\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 36, '2025-11-01 01:40:05', '2025-11-01 04:37:11', NULL, NULL),
(37, 'Forklift Stacker Management System', 'Forklift Stacker Management System - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web Development, SATNUSA, Industrial Solutions', 'Forklift Stacker Management System | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/38_forklift-stacker-management-system.png', '/projects/forklift-stacker-management-system', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Forklift Stacker Management System\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Forklift Stacker Management System\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'forklift-stacker-management-system', 'Empower your business with a powerful web application that combines elegant design with cutting-edge functionality. From seamless user interactions to advanced data management, every feature is crafted for optimal performance.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/38_forklift-stacker-management-system.png', NULL, 'Web Development', NULL, 'completed', NULL, '[]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 37, '2025-11-01 01:40:05', '2025-11-01 04:37:11', NULL, NULL),
(38, 'Ahu System Monitoring', 'Ahu System Monitoring - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web, Real-time Monitoring, Sensors, Web Development, SATNUSA, Industrial Solutions', 'Ahu System Monitoring | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/39_ahu-system-monitoring.png', '/projects/ahu-system-monitoring', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Ahu System Monitoring\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Ahu System Monitoring\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'ahu-system-monitoring', 'Experience digital transformation with a feature-rich application that puts users first. This solution integrates seamlessly with existing systems, automates complex workflows, and provides actionable insights through intuitive dashboards.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/39_ahu-system-monitoring.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Web\",\"Real-time Monitoring\",\"Sensors\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 38, '2025-11-01 01:40:06', '2025-11-01 04:37:11', NULL, NULL),
(39, 'Digital Fire Fighter Alarm System Notifications Through Mysatnusa App', 'Digital Fire Fighter Alarm System Notifications Through Mysa', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Mobile Application, Web Development, SATNUSA, Industrial Solutions', 'Digital Fire Fighter Alarm System Notifications... | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/40_digital-fire-fighter-alarm-system-notifications-through-mysatnusa-app.png', '/projects/digital-fire-fighter-alarm-system-notifications-through-mysatnusa-app', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Digital Fire Fighter Alarm System Notifications Through Mysatnusa App\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Digital Fire Fighter Alarm System Notifications Through Mysatnusa App\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'digital-fire-fighter-alarm-system-notifications-through-mysatnusa-app', 'Empower your business with a powerful web application that combines elegant design with cutting-edge functionality. From seamless user interactions to advanced data management, every feature is crafted for optimal performance.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/40_digital-fire-fighter-alarm-system-notifications-through-mysatnusa-app.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Mobile Application\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 39, '2025-11-01 01:40:06', '2025-11-01 04:37:11', NULL, NULL),
(40, 'Rpa Project For Routine Operational', 'Rpa Project For Routine Operational - Automation', 'An intelligent automation solution that eliminates manual processes and enhances operational efficiency.', 'Automation, SATNUSA, Industrial Solutions', 'Rpa Project For Routine Operational | SATNUSA', 'An intelligent automation solution that eliminates manual processes and enhances operational efficiency. This project implements workflow automation, data processing, and system integration to reduce ', '/storage/projects/41_rpa-project-for-routine-operational.png', '/projects/rpa-project-for-routine-operational', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Rpa Project For Routine Operational\",\"description\":\"An intelligent automation solution that eliminates manual processes and enhances operational efficiency. This project implements workflow automation, data processing, and system integration to reduce human error and accelerate business operations. The platform is designed to handle repetitive tasks with precision and consistency, freeing up valuable human resources for more strategic activities. With its flexible configuration and scalable architecture, this automation system adapts to evolving business needs while maintaining reliability and performance. It represents a significant step towar\",\"applicationCategory\":\"Automation\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Rpa Project For Routine Operational\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'rpa-project-for-routine-operational', 'An innovative solution combining modern technology with practical functionality. This project demonstrates expertise in problem-solving, user-centric design, and delivering measurable results that drive business success.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/41_rpa-project-for-routine-operational.png', NULL, 'Automation', NULL, 'completed', NULL, '[]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 40, '2025-11-01 01:40:06', '2025-11-01 04:37:11', NULL, NULL),
(41, 'Mysatnusa Super Apps', 'Mysatnusa Super Apps - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web, Mobile Application, Web Development, SATNUSA, Industrial Solutions', 'Mysatnusa Super Apps | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/42_mysatnusa-super-apps.png', '/projects/mysatnusa-super-apps', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Mysatnusa Super Apps\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Mysatnusa Super Apps\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'mysatnusa-super-apps', 'Experience digital transformation with a feature-rich application that puts users first. This solution integrates seamlessly with existing systems, automates complex workflows, and provides actionable insights through intuitive dashboards.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/42_mysatnusa-super-apps.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Web\",\"Mobile Application\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 41, '2025-11-01 01:40:06', '2025-11-01 04:37:11', NULL, NULL),
(42, 'E-Kiosk Automated Mobile Device Security Inspection', 'E-Kiosk Automated Mobile Device Security Inspection', 'A user-friendly mobile application designed to provide on-the-go access to essential business functions.', 'Mobile, Computer Vision, Quality Control, Mobile Application, Security, Mobile Apps, SATNUSA, Industrial Solutions', 'E-Kiosk Automated Mobile Device Security Inspec... | SATNUSA', 'A user-friendly mobile application designed to provide on-the-go access to essential business functions. This solution combines intuitive design with powerful functionality, enabling users to perform ', '/storage/projects/43_e-kiosk-automated-mobile-device-security-inspection.png', '/projects/e-kiosk-automated-mobile-device-security-inspection', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"E-Kiosk Automated Mobile Device Security Inspection\",\"description\":\"A user-friendly mobile application designed to provide on-the-go access to essential business functions. This solution combines intuitive design with powerful functionality, enabling users to perform critical tasks from anywhere, at any time. The app features offline capabilities, real-time synchronization, and seamless integration with existing systems. Built with cross-platform compatibility in mind, it delivers a consistent experience across different devices while maintaining high performance and security standards. This mobile solution empowers teams with instant access to information and\",\"applicationCategory\":\"Mobile Apps\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: E-Kiosk Automated Mobile Device Security Inspection\n\nThis platforms project implements Mobile to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Mobile\"]}', 80, 1, 'e-kiosk-automated-mobile-device-security-inspection', 'Experience next-generation automated inspection powered by deep learning algorithms. Our system processes thousands of images per hour, catches defects human eyes might miss, and provides detailed analytics for continuous improvement.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/43_e-kiosk-automated-mobile-device-security-inspection.png', NULL, 'Mobile Apps', NULL, 'completed', NULL, '[\"Mobile\",\"Computer Vision\",\"Quality Control\",\"Mobile Application\",\"Security\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 42, '2025-11-01 01:40:06', '2025-11-01 04:37:11', NULL, NULL),
(43, 'Internship Attendance System For Attendance Of Intern Employees', 'Internship Attendance System For Attendance Of Intern Employ', 'An intelligent automation solution that eliminates manual processes and enhances operational efficiency.', 'Attendance, Automation, SATNUSA, Industrial Solutions', 'Internship Attendance System For Attendance Of ... | SATNUSA', 'An intelligent automation solution that eliminates manual processes and enhances operational efficiency. This project implements workflow automation, data processing, and system integration to reduce ', '/storage/projects/44_internship-attendance-system-for-attendance-of-intern-employees.png', '/projects/internship-attendance-system-for-attendance-of-intern-employees', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Internship Attendance System For Attendance Of Intern Employees\",\"description\":\"An intelligent automation solution that eliminates manual processes and enhances operational efficiency. This project implements workflow automation, data processing, and system integration to reduce human error and accelerate business operations. The platform is designed to handle repetitive tasks with precision and consistency, freeing up valuable human resources for more strategic activities. With its flexible configuration and scalable architecture, this automation system adapts to evolving business needs while maintaining reliability and performance. It represents a significant step towar\",\"applicationCategory\":\"Automation\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Internship Attendance System For Attendance Of Intern Employees\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'internship-attendance-system-for-attendance-of-intern-employees', 'Empower your business with a powerful web application that combines elegant design with cutting-edge functionality. From seamless user interactions to advanced data management, every feature is crafted for optimal performance.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/44_internship-attendance-system-for-attendance-of-intern-employees.png', NULL, 'Automation', NULL, 'completed', NULL, '[\"Attendance\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 43, '2025-11-01 01:40:06', '2025-11-01 04:37:11', NULL, NULL),
(44, 'E-Leave Form Request Mysatnusa App', 'E-Leave Form Request Mysatnusa App - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Mobile Application, Web Development, SATNUSA, Industrial Solutions', 'E-Leave Form Request Mysatnusa App | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/45_e-leave-form-request-mysatnusa-app.png', '/projects/e-leave-form-request-mysatnusa-app', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"E-Leave Form Request Mysatnusa App\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: E-Leave Form Request Mysatnusa App\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'e-leave-form-request-mysatnusa-app', 'Experience digital transformation with a feature-rich application that puts users first. This solution integrates seamlessly with existing systems, automates complex workflows, and provides actionable insights through intuitive dashboards.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/45_e-leave-form-request-mysatnusa-app.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Mobile Application\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 44, '2025-11-01 01:40:06', '2025-11-01 04:37:11', NULL, NULL),
(45, 'E-Bus Form Request Mysatnusa App', 'E-Bus Form Request Mysatnusa App - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Mobile Application, Web Development, SATNUSA, Industrial Solutions', 'E-Bus Form Request Mysatnusa App | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/46_e-bus-form-request-mysatnusa-app.png', '/projects/e-bus-form-request-mysatnusa-app', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"E-Bus Form Request Mysatnusa App\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: E-Bus Form Request Mysatnusa App\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'e-bus-form-request-mysatnusa-app', 'A comprehensive digital solution designed to streamline operations and enhance user experience. Built with modern technologies and best practices, this platform delivers robust functionality, intuitive interface, and scalable architecture.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/46_e-bus-form-request-mysatnusa-app.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Mobile Application\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 45, '2025-11-01 01:40:06', '2025-11-01 04:37:11', NULL, NULL),
(46, 'Mysatnusa V-Card Spsi', 'Mysatnusa V-Card Spsi - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web Development, SATNUSA, Industrial Solutions', 'Mysatnusa V-Card Spsi | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/47_mysatnusa-v-card-spsi.png', '/projects/mysatnusa-v-card-spsi', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Mysatnusa V-Card Spsi\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Mysatnusa V-Card Spsi\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'mysatnusa-v-card-spsi', 'An innovative solution combining modern technology with practical functionality. This project demonstrates expertise in problem-solving, user-centric design, and delivering measurable results that drive business success.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/47_mysatnusa-v-card-spsi.png', NULL, 'Web Development', NULL, 'completed', NULL, '[]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 46, '2025-11-01 01:40:06', '2025-11-01 04:37:11', NULL, NULL),
(47, 'Digital Access Form Request It-Infra', 'Digital Access Form Request It-Infra - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web, Web Development, SATNUSA, Industrial Solutions', 'Digital Access Form Request It-Infra | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/48_digital-access-form-request-it-infra.png', '/projects/digital-access-form-request-it-infra', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Digital Access Form Request It-Infra\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Digital Access Form Request It-Infra\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'digital-access-form-request-it-infra', 'An innovative solution combining modern technology with practical functionality. This project demonstrates expertise in problem-solving, user-centric design, and delivering measurable results that drive business success.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/48_digital-access-form-request-it-infra.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Web\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 47, '2025-11-01 01:40:06', '2025-11-01 04:37:11', NULL, NULL),
(48, 'Dlp Form Request Cybersecurity', 'Dlp Form Request Cybersecurity - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Security, Web Development, SATNUSA, Industrial Solutions', 'Dlp Form Request Cybersecurity | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/49_dlp-form-request-cybersecurity.png', '/projects/dlp-form-request-cybersecurity', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Dlp Form Request Cybersecurity\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Dlp Form Request Cybersecurity\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 80, 1, 'dlp-form-request-cybersecurity', 'Protect your assets with intelligent monitoring and real-time threat detection. This advanced security system leverages AI to identify anomalies, track activities, and provide instant alerts, ensuring round-the-clock protection.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/49_dlp-form-request-cybersecurity.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Security\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 48, '2025-11-01 01:40:07', '2025-11-02 00:58:58', NULL, '[\"44\",\"45\",\"47\"]'),
(49, 'Asset Audit', 'Asset Audit - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web, Web Development, SATNUSA, Industrial Solutions', 'Asset Audit | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/50_asset-audit.png', '/projects/asset-audit', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Asset Audit\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Asset Audit\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 70, 1, 'asset-audit', 'An innovative solution combining modern technology with practical functionality. This project demonstrates expertise in problem-solving, user-centric design, and delivering measurable results that drive business success.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/50_asset-audit.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Web\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 49, '2025-11-01 01:40:07', '2025-11-01 04:37:11', NULL, NULL),
(50, 'Smt Store Room Using Iot For  Auto Humidity Control', 'Smt Store Room Using Iot For  Auto Humidity Control', 'A sophisticated IoT solution that connects physical devices with digital intelligence to enable smart monitoring and control.', 'IoT, Real-time Monitoring, Sensors, SATNUSA, Industrial Solutions', 'Smt Store Room Using Iot For  Auto Humidity Con... | SATNUSA', 'A sophisticated IoT solution that connects physical devices with digital intelligence to enable smart monitoring and control. This project implements sensor networks, real-time data collection, and au', '/storage/projects/51_smt-store-room-using-iot-for-auto-humidity-control.png', '/projects/smt-store-room-using-iot-for-auto-humidity-control', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Smt Store Room Using Iot For  Auto Humidity Control\",\"description\":\"A sophisticated IoT solution that connects physical devices with digital intelligence to enable smart monitoring and control. This project implements sensor networks, real-time data collection, and automated response systems to optimize operational workflows. The platform provides comprehensive visibility into equipment status, environmental conditions, and system performance, allowing for proactive maintenance and immediate issue resolution. With its robust architecture and scalable design, this IoT system transforms traditional operations into a connected, data-driven ecosystem that enhances\",\"applicationCategory\":\"IoT\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Smt Store Room Using Iot For  Auto Humidity Control\n\nThis platforms project implements IoT to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"IoT\"]}', 90, 1, 'smt-store-room-using-iot-for-auto-humidity-control', 'Launch your online presence with a modern e-commerce platform designed for growth. From seamless checkout experiences to powerful inventory management, this solution provides everything needed to succeed in digital commerce.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/51_smt-store-room-using-iot-for-auto-humidity-control.png', NULL, 'IoT', NULL, 'completed', NULL, '[\"IoT\",\"Real-time Monitoring\",\"Sensors\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 50, '2025-11-01 01:40:07', '2025-11-01 04:37:12', NULL, NULL),
(51, 'Smt-Smart Rack Esd Grounding Monitoring', 'Smt-Smart Rack Esd Grounding Monitoring - IoT Platform', 'A sophisticated IoT solution that connects physical devices with digital intelligence to enable smart monitoring and control.', 'Real-time Monitoring, Sensors, IoT, SATNUSA, Industrial Solutions', 'Smt-Smart Rack Esd Grounding Monitoring | SATNUSA', 'A sophisticated IoT solution that connects physical devices with digital intelligence to enable smart monitoring and control. This project implements sensor networks, real-time data collection, and au', '/storage/projects/52_smt-smart-rack-esd-grounding-monitoring.png', '/projects/smt-smart-rack-esd-grounding-monitoring', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Smt-Smart Rack Esd Grounding Monitoring\",\"description\":\"A sophisticated IoT solution that connects physical devices with digital intelligence to enable smart monitoring and control. This project implements sensor networks, real-time data collection, and automated response systems to optimize operational workflows. The platform provides comprehensive visibility into equipment status, environmental conditions, and system performance, allowing for proactive maintenance and immediate issue resolution. With its robust architecture and scalable design, this IoT system transforms traditional operations into a connected, data-driven ecosystem that enhances\",\"applicationCategory\":\"IoT\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Smt-Smart Rack Esd Grounding Monitoring\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 90, 1, 'smt-smart-rack-esd-grounding-monitoring', 'Enhance safety and security with smart surveillance technology. Our system combines high-definition monitoring, intelligent analytics, and automated alerts to create a comprehensive security ecosystem.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/52_smt-smart-rack-esd-grounding-monitoring.png', NULL, 'IoT', NULL, 'completed', NULL, '[\"Real-time Monitoring\",\"Sensors\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 51, '2025-11-01 01:40:07', '2025-11-01 04:37:12', NULL, NULL),
(52, 'Smt Dashboard Achievement', 'Smt Dashboard Achievement - Web Platform', 'A comprehensive web-based platform designed to streamline data visualization and performance monitoring and enhance user experience.', 'Web, Dashboard, Analytics, Web Development, SATNUSA, Industrial Solutions', 'Smt Dashboard Achievement | SATNUSA', 'A comprehensive web-based platform designed to streamline data visualization and performance monitoring and enhance user experience. This solution features an intuitive interface, robust backend archi', '/storage/projects/53_smt-dashboard-achievement.png', '/projects/smt-dashboard-achievement', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Smt Dashboard Achievement\",\"description\":\"A comprehensive web-based platform designed to streamline data visualization and performance monitoring and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Smt Dashboard Achievement\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 90, 1, 'smt-dashboard-achievement', 'Unlock the power of your data with intelligent analytics and visualization. This comprehensive platform transforms raw data into actionable insights, enabling data-driven decisions and revealing patterns that drive business growth.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/53_smt-dashboard-achievement.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Web\",\"Dashboard\",\"Analytics\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 52, '2025-11-01 01:40:07', '2025-11-01 04:37:12', NULL, NULL),
(53, 'Smt Cargo Lift Controlling Monitoring', 'Smt Cargo Lift Controlling Monitoring - IoT Platform', 'A sophisticated IoT solution that connects physical devices with digital intelligence to enable smart monitoring and control.', 'Real-time Monitoring, Sensors, IoT, SATNUSA, Industrial Solutions', 'Smt Cargo Lift Controlling Monitoring | SATNUSA', 'A sophisticated IoT solution that connects physical devices with digital intelligence to enable smart monitoring and control. This project implements sensor networks, real-time data collection, and au', '/storage/projects/54_smt-cargo-lift-controlling-monitoring.png', '/projects/smt-cargo-lift-controlling-monitoring', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Smt Cargo Lift Controlling Monitoring\",\"description\":\"A sophisticated IoT solution that connects physical devices with digital intelligence to enable smart monitoring and control. This project implements sensor networks, real-time data collection, and automated response systems to optimize operational workflows. The platform provides comprehensive visibility into equipment status, environmental conditions, and system performance, allowing for proactive maintenance and immediate issue resolution. With its robust architecture and scalable design, this IoT system transforms traditional operations into a connected, data-driven ecosystem that enhances\",\"applicationCategory\":\"IoT\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Smt Cargo Lift Controlling Monitoring\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 90, 1, 'smt-cargo-lift-controlling-monitoring', 'Protect your assets with intelligent monitoring and real-time threat detection. This advanced security system leverages AI to identify anomalies, track activities, and provide instant alerts, ensuring round-the-clock protection.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/54_smt-cargo-lift-controlling-monitoring.png', NULL, 'IoT', NULL, 'completed', NULL, '[\"Real-time Monitoring\",\"Sensors\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 53, '2025-11-01 01:40:07', '2025-11-01 04:37:12', NULL, NULL);
INSERT INTO `projects` (`id`, `title`, `meta_title`, `meta_description`, `meta_keywords`, `og_title`, `og_description`, `og_image`, `canonical_url`, `schema_markup`, `ai_summary`, `tech_stack_details`, `seo_score`, `index_follow`, `slug`, `description`, `impact_statement`, `content`, `context`, `role`, `problem`, `solution`, `integration`, `result`, `image`, `images`, `category`, `domain`, `status`, `technologies`, `tags`, `client`, `url`, `github_url`, `completed_at`, `start_date`, `end_date`, `featured`, `published`, `is_active`, `sort_order`, `created_at`, `updated_at`, `deleted_at`, `related_project_ids`) VALUES
(54, 'Dashboard Router', 'Dashboard Router - Web Platform', 'A comprehensive web-based platform designed to streamline data visualization and performance monitoring and enhance user experience.', 'Dashboard, Analytics, Web Development, SATNUSA, Industrial Solutions', 'Dashboard Router | SATNUSA', 'A comprehensive web-based platform designed to streamline data visualization and performance monitoring and enhance user experience. This solution features an intuitive interface, robust backend archi', '/storage/projects/55_dashboard-router.png', '/projects/dashboard-router', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Dashboard Router\",\"description\":\"A comprehensive web-based platform designed to streamline data visualization and performance monitoring and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Dashboard Router\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 90, 1, 'dashboard-router', 'Unlock the power of your data with intelligent analytics and visualization. This comprehensive platform transforms raw data into actionable insights, enabling data-driven decisions and revealing patterns that drive business growth.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/55_dashboard-router.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Dashboard\",\"Analytics\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 54, '2025-11-01 01:40:07', '2025-11-01 04:37:12', NULL, NULL),
(55, 'Electronic Medical Record', 'Electronic Medical Record - Web Platform', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience.', 'Web, Web Development, SATNUSA, Industrial Solutions', 'Electronic Medical Record | SATNUSA', 'A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless inte', '/storage/projects/56_electronic-medical-record.png', '/projects/electronic-medical-record', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Electronic Medical Record\",\"description\":\"A comprehensive web-based platform designed to streamline business operations and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project tracking, delivering meas\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Electronic Medical Record\n\nThis platforms project implements Web to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[\"Web\"]}', 80, 1, 'electronic-medical-record', 'An innovative solution combining modern technology with practical functionality. This project demonstrates expertise in problem-solving, user-centric design, and delivering measurable results that drive business success.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/56_electronic-medical-record.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Web\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 55, '2025-11-01 01:40:07', '2025-11-01 04:37:12', NULL, NULL),
(56, 'Dashboard Production Quality', 'Dashboard Production Quality - Web Platform', 'A comprehensive web-based platform designed to streamline data visualization and performance monitoring and enhance user experience.', 'Dashboard, Analytics, Production, Web Development, SATNUSA, Industrial Solutions', 'Dashboard Production Quality | SATNUSA', 'A comprehensive web-based platform designed to streamline data visualization and performance monitoring and enhance user experience. This solution features an intuitive interface, robust backend archi', '/storage/projects/57_dashboard-production-quality.png', '/projects/dashboard-production-quality', '{\"@context\":\"https:\\/\\/schema.org\",\"@type\":\"SoftwareApplication\",\"name\":\"Dashboard Production Quality\",\"description\":\"A comprehensive web-based platform designed to streamline data visualization and performance monitoring and enhance user experience. This solution features an intuitive interface, robust backend architecture, and seamless integration capabilities. The system provides centralized access to critical business functions, enabling teams to collaborate effectively and make data-driven decisions. Built with modern web technologies, this platform ensures high performance, security, and scalability. It serves as a central hub for managing various operational aspects, from employee management to project\",\"applicationCategory\":\"Web Development\",\"author\":{\"@type\":\"Organization\",\"name\":\"SATNUSA\"}}', 'Technical Overview: Dashboard Production Quality\n\nThis platforms project implements modern technology stack to address specific operational challenges. The application provides a comprehensive digital solution for managing critical business operations. Key technical features include real-time data processing, scalable architecture, and user-friendly interfaces. The implementation focuses on reliability, performance optimization, and seamless integration with existing infrastructure. This solution demonstrates practical application of modern technology to solve real-world business challenges.', '{\"frontend\":[],\"backend\":[],\"tools\":[],\"platforms\":[]}', 90, 1, 'dashboard-production-quality', 'Unlock the power of your data with intelligent analytics and visualization. This comprehensive platform transforms raw data into actionable insights, enabling data-driven decisions and revealing patterns that drive business growth.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'projects/thumbnail/57_dashboard-production-quality.png', NULL, 'Web Development', NULL, 'completed', NULL, '[\"Dashboard\",\"Analytics\",\"Production\"]', 'SATNUSA', NULL, NULL, NULL, NULL, NULL, 0, 1, 1, 56, '2025-11-01 01:40:07', '2025-11-01 04:37:12', NULL, NULL);

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

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('47ZixP35pM7zRl6lPg5CgVj0e5O4iBkEkLvaQtUZ', NULL, '31.97.188.145', 'curl/8.5.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiVG5pdjhFN2p2bXZ4UFBRaEFmTW4weXRsMlBUQkVkY0hhb3ZyREw1cCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MjM6Imh0dHA6Ly9hbGlzYWRpa2lubWEuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1762110981),
('AiynzLRFGWtk67hCYbBJFHctXNm6JQ2whWa9Y9AC', NULL, '180.242.199.158', 'WhatsApp/2.23.20.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoicFV2WUxHQXU4aXlzcnJpblZrY1lHSmdhNUNtams0eHhaTlE0MlBqSyI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MjQ6Imh0dHBzOi8vYWxpc2FkaWtpbm1hLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1762112044),
('fbtUE9xneO5Vm8XK8N6tYdFtoZXbTzXfzFFAJW6k', NULL, '180.242.199.158', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiclRCZkNzSmZpYVZreVRUQmxzWXRFeEoxQWJ1ZXdnT05KaWc4a0JuTCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MjQ6Imh0dHBzOi8vYWxpc2FkaWtpbm1hLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1762112136),
('tCOxaG4XIaEHlHD4UseVg7BhsWUTUnBlJECJskWJ', NULL, '180.242.199.158', 'WhatsApp/2.23.20.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiUVNkcHg0SGFOMVQ3OGhvZ0lLbHFzSFlsZFJ3bjJIME93T2FRZUllUCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MjQ6Imh0dHBzOi8vYWxpc2FkaWtpbm1hLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1762112044);

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
(1, 'statistics', '{\"years_experience\":\"17+\",\"followers\":\"57K\",\"projects_delivered\":\"56+\",\"cost_savings\":\"$318K+\",\"success_rate\":\"95%\"}', 'about', 'json', '2025-10-31 10:59:32', '2025-11-02 08:26:58'),
(2, 'name', 'Ali Sadikin Ma', 'about', 'text', '2025-10-31 11:04:22', '2025-11-02 10:05:47'),
(3, 'title', 'AI Automation Architect & Technology Innovator', 'about', 'text', '2025-10-31 11:04:22', '2025-11-03 00:38:03'),
(4, 'bio', 'AI Automation Architect | Bridging AI, Web, and Industrial Systems into Scalable Automation.\r\nBuilding intelligent tech that transforms operations and accelerates innovation.', 'about', 'text', '2025-10-31 11:04:22', '2025-11-03 00:38:03'),
(5, 'profile_photo', '/uploads/about/1761935897_alisadikin_profile_photo.png', 'about', 'image', '2025-10-31 11:04:22', '2025-11-02 08:27:16'),
(6, 'skills', '[\"AI Automation Architecture\",\"Workflow Automation\",\"Edge AI\",\"Python\",\"Vue.js\",\"Laravel\",\"n8n Automation\",\"Industrial IoT\",\"Full-Stack Development\",\"AI Integration\",\"System Architecture\",\"Digital Transformation Strategy\"]', 'about', 'json', '2025-10-31 11:04:22', '2025-11-03 00:38:03'),
(7, 'experience', '[{\"position\":\"Senior Full-Stack Developer\",\"company\":\"eXSYS Pte Ltd\",\"period\":\"2020 - Present\",\"description\":\"- Led the design and improvement of systems including eApps, SMS, and Finance, utilizing technologies like J2EE, Struts, Spring, Hibernate, and Oracle DB to boost system performance, increase reliability, and enhance user satisfaction.\\n- Optimized and managed Oracle DB to drive database performance and ensure seamless data handling.\\n- Improved the Application Management (AM) system\'s performance by providing targeted technical support, which enhanced system response times and resolved 95% of reported issues efficiently.\\n- Utilized Linux Shell scripting to automate complex data processes, significantly increasing operational efficiency.\\n- Executed comprehensive testing protocols to ensure optimal system performance and quality assurance.\\n- Collaborated with stakeholders to capture requirements and deliver strategic, expert recommendations.\\n- Facilitated flawless integration across diverse management systems, enhancing overall system synergy.\\nDeveloped extensive technical documentation and conduct impactful training sessions for client teams.\",\"gallery_ids\":[],\"company_url\":\"https:\\/\\/exsys.com.sg\\/\",\"title\":\"Software Consultant\",\"company_logo\":\"https:\\/\\/s-yoolk-images.s3.amazonaws.com\\/sg\\/gallery_images\\/medium\\/1434459272\\/508139?1434459272\",\"location\":\"Singapore\",\"start_date\":\"Apr 2008\",\"end_date\":\"Sep 2011\"},{\"position\":\"Full-Stack Developer\",\"company\":\"DHL Supply Chain PTE LTD\",\"period\":\"2017 - 2020\",\"description\":\"- Engineered dynamic web portals using J2EE, Struts, Spring, Hibernate, and Oracle DB.\\n- Administered Oracle Essbase for advanced multidimensional business analytics, enhancing data accuracy, optimizing report generation, and streamlining decision-making processes across multiple departments.\\n- Streamlined processes with Linux shell scripting for data population and task scheduling.\\n- Delivered robust support for systems and databases at DHL Supply Chain Asia Pacific.\\n- Enabled insightful business analysis using multidimensional databases for executive decision-making.\\n- Monitored and enhanced system and database performance, leading to a 25% reduction in system downtime and increasing application speed and reliability across multiple platforms.\\n- Collaborated with stakeholders to align on business and technical requirements, resulting in a 15% increase in project efficiency and a 20% boost in stakeholder satisfaction.\\n- Reviewed security procedures regularly to ensure adherence to strict standards, safeguarding data integrity and preventing unauthorized access, resulting in zero data breaches in two years.\\n- Developed comprehensive technical documentation and facilitated training for internal teams, enhancing team knowledge and efficiency.\",\"gallery_ids\":[],\"company_url\":\"https:\\/\\/www.dhl.com\\/sg-en\\/home\\/supply-chain.html\",\"title\":\"Software Engineer\",\"company_logo\":\"https:\\/\\/upload.wikimedia.org\\/wikipedia\\/commons\\/e\\/e8\\/DHL_Supply_Chain_logo.png\",\"location\":\"Singapore\",\"start_date\":\"Oct 2011\",\"end_date\":\"Apr 2013\"},{\"position\":\"Frontend Developer\",\"company\":\"Thales Digital Identity and Security (ex Gemalto)\",\"period\":\"2015 - 2017\",\"description\":\"- Collaborated with TELCO clients across the Asia Pacific region to provide expert technical support, leading to a 30% increase in client satisfaction scores and a significant reduction in system downtime.\\n- Coordinated and executed seamless system integration at customer locations, traveling as part of deployments, which resulted in a 20% increase in operational efficiency and strengthened client trust through reliable and timely implementation.\\n- Enhanced and troubleshoot applications utilizing J2EE, Struts, Spring, Hibernate, and Oracle DB, leading to increased application performance and reliability.\\n- Leveraged Linux Shell scripting to streamline tasks and optimize operational processes, resulting in reduced manual effort and increased efficiency.\\n- Educated customer teams and produced comprehensive technical documentation, which improved team knowledge and facilitated smoother project execution.\\n- Collaborated with internal teams to resolve technical challenges using MySQL and Struts, enhancing customer satisfaction by improving response times.\\n- Monitored and improved system performance using multidimensional databases, resulting in increased efficiency and reduced downtime.\",\"gallery_ids\":[],\"company_url\":\"https:\\/\\/www.thalesgroup.com\\/en\\/worldwide\\/singapore\",\"title\":\"Solution Support Engineer\",\"company_logo\":\"https:\\/\\/d1sr9z1pdl3mb7.cloudfront.net\\/wp-content\\/uploads\\/2020\\/06\\/08115531\\/Thales_LOGO_RGB-1024x385.png\",\"location\":\"Singapore\",\"start_date\":\"May 2013\",\"end_date\":\"Apr 2015\"},{\"title\":\"Application Development Analyst \",\"company\":\"Maritime and Port Authority of Singapore (MPA)\",\"company_logo\":\"https:\\/\\/www.mpa.gov.sg\\/images\\/mpalibraries\\/mpa-library\\/shared\\/header\\/mpa_logo_pos16df72126c604b5bbcb173c5f4b1501b.png?sfvrsn=9bf3952_0\",\"company_url\":\"https:\\/\\/www.mpa.gov.sg\\/home\",\"location\":\"Singapore\",\"start_date\":\"May 2015\",\"end_date\":\"Nov 2015\",\"description\":\"- Collaborated with stakeholders to analyze and define requirements, leading to improved vessel and crew registration applications.\\n- Developed web-based applications for vessel and crew registration, improving compliance with port regulations by 20% while enhancing user experience and operational efficiency.\\n- Developed robust access management features using J2EE for the Singapore port system, significantly enhancing security and streamlining user access control protocol.\\n- Created advanced functionalities for crew sailing license verification using AI frameworks, ensuring full compliance with Singapore Port regulations, and reducing manual errors by 30%, which improved overall licensing processing efficiency.\\n- Enhanced data storage and retrieval systems using Oracle Database and PL\\/SQL, ensuring data integrity and security, which resulted in improved system reliability and a 25% increase in data access efficiency.\\n- Enhanced application performance and scalability by implementing Enterprise JavaBeans (EJB) and JavaServer Pages (JSP), leading to improved system efficiency and delivering a smoother user experience across multiple platforms.\\n- Conducted rigorous testing and debugging, ensuring robust application functionality and user satisfaction.\",\"current\":false,\"gallery_ids\":[]},{\"title\":\"Co-Founder & CEO\",\"company\":\"Marlin Booking\",\"company_logo\":\"https:\\/\\/encrypted-tbn0.gstatic.com\\/images?q=tbn:ANd9GcT_J7RjJH7X42mmIxLTdrMUra4AXeGNT_5Dyg&s\",\"company_url\":\"https:\\/\\/www.techinasia.com\\/companies\\/marlin-booking\",\"location\":\"Batam - Indonesia\",\"start_date\":\"May 2016\",\"end_date\":\"Nov 2019\",\"description\":\"- Digitized Indonesian ports in collaboration with the Ministry of Transportation, including ports in Harbourbay, Palembang, Murhum, and Tulehu, boosting efficiency.\\n- Developed mobile ticketing for seamless travel bookings to Singapore and Malaysia, improving user engagement and increasing booking efficiency, resulting in a 20% rise in customer satisfaction ratings and a 15% increase in sales within six months.\\n- Developed Passenger Service System Modules to support government port initialization, enhancing operational efficiency and ensuring compliance with regulations for smoother and reliable port operations.\",\"current\":false,\"gallery_ids\":[]},{\"title\":\"Head of Digital Transformation Department & Automation\",\"company\":\"PT. Sat Nusapersada Tbk\",\"company_logo\":\"data:image\\/png;base64,iVBORw0KGgoAAAANSUhEUgAAAOEAAADhCAMAAAAJbSJIAAAAmVBMVEX\\/\\/\\/\\/tMjftMDXtKi\\/+9vbsJivtNzzyiYvsHiX96+zrFx\\/xdHf5x8jsIin+8vLsGSL83+D4vb\\/uRkrxd3r85ebrERrvbW\\/\\/+vr72tv5ysv96en6z9DuT1PtPED2r7DuSk7uVFj4wMH1pKb3uLnvW17wZ2rzh4n0lpjyfoD1oaPrBhP3srP0mZz61dXzj5LvYmXqAADPz8\\/s7OxcqMvzAAAQUUlEQVR4nO2daWOCuhKGIYiNKGJV3Opa61Kttff8\\/x93JTt7wpJ6eni\\/larwAJlMJpOJYTRq1KhRo0aNGjVq1KhRo0aNGjVq1OhX1dKqXwDcW22NMrfaAecO0Cr3oBlw4AFTr+BZK+DY0g1oms63RsD+0NIOaJr+Wh8hAbS0Cb8x\\/k4X4IeNTuh+jLp6NAIIEThzPYAjiADtDz2nC7TDhg3YAx1ne\\/HwK7qc6jgb0dXBJwWd+s\\/16eNztfv1n0vQFzntsPb7er2gMwEdNzMk8urY25oRexA3CHijR\\/qLesVOTZq\\/O6kV8OZiQL9Hj8xq7idc5spMiQn36nRuxpB0THt6ZOHW7ZA6J4ZIumH\\/qzbAvkkA39mRVf3OjcPPBghiXc5Na4lPwB3E1tauHdAEHntjBrTnr8m5Ie0AdtmRu\\/v423bqFAha\\/YyecO4QOzBLvMKSOhBbdggfqXdUM7MRInNlej5BrMG5ORJXZsuOfAd+Rs3G2+ihp9hmncaa9PyrRda3iuiLuE0rFjH5dBBw3T4GcjGsITstdW42FYduqF9ojumRvR8Grk3oTtpb9veROP7b1G8U0e6CX3+LtfBecGuBqcN3Q\\/6a0BomLjYIVbaPN2LCnDd65Bb0\\/cDSMpgxRgESPLK\\/t7jXEo6U1YCMsX9YNzQGyMS9ZX2rQi2DfspjrkyL+BnOZ0W\\/37HIDzJXotMOjlx6Wd\\/CH7zt3k+j+3K4Wg2Hy\\/vkeHrfvw2U2+50g87Hzt9v4yu6VOPcTMkd8yIOIvemkr82eB8Nbd+BtmURF\\/PhSNvQ8z1rc16PlWzwAl2D4O\\/bxH\\/Mv8kS2sZceuTcOFn+77R3HvrQAskxxwer529OKo0YNRRhzPbmR0dxxUUtF3dlusERL6OZL75NaOcFVIELuwphgtkFmTbWWe0IIiht7M5kaM3DTsegf4Lppnp2uOTiYUZLJTyIkIDFbsqa9tAloykn8jtL9jvIp0iPs427vvyASik8iJCsJTNT9NI2pbwq4gUCi\\/XsV+TKDNPM4ckO8wX2xXbhQy6yOBFEa6hwMafg1O6d\\/T0ir9c94zt52hNAj7m5r8G7khpnm7WhwAAs14Pt5eTlc329Xt+\\/u9uV60Ve4IvKMAg5\\/0L7OGATkdFi8jQncViPmzDkyrjj5M+vfeHyLWh9fM0XoVdouuh9L6ErfEptLDsJ2zgauXFe1LiYBiSu5rGrGAQXJwCHdRQALf9jnTK+mb1AqyAhRvKZK0ODKJfsrjlNfTofwl2JIE4C0gxg1xMsyEeWQzfeuMUICZLDwhpj8j7wIyo\\/RjwjJ+IO+tfkz58FQJh3wgksRkhuMvcXZ7HwprRaxIH32Ds+RUf8lBfi0xEsTH4vfLeKEeK5WeCELUMh5+ZO7BQPOyHL5aS4MjeHt0FHYsgxIDb1ojw8QcEoAFgj35NpBksxrBGfQUNvIRylfL7NAV2p2ehJ4I5bfoH8g1ccXGAd1juN3Cg5N98k7LRixh55EKmd61V8R6Vu5pdngeFHEQOB3RBhcu+7wHTfpxO9LehGpTtIK6GfkJs4bY0XhSMg6G67wutF4pzyU7Z7ErTgnvyrk+XKPJo7AzTtU8qHKhRCEloMNRqyods5absODzWjoQtIcWUM48XlhFBHxgSyetzM09QJyYSUGbG\\/TrjTAW66Pf4Q3G23mH+hqEjPRdNf0vqykMZkAMB79oWJoiTpAx06J4XbYTf1cxWqhZD4LB+9ahnnht4N5vy1MOBr+lcW3J15POtVuWuXVAdH+9htnxHbcUltSky4Z\\/PYG43jXJlhp4FIaOa6bNUIz9lAbiuw3y8xK4UILW6ncKPObMJhQqApSwMFowTzt0fmTmJSChF67CKjhjlJobf0YWu0tMRHF4bmbPjMCbJ3yoQvqHPNiRN0IpmKWXG4KkXcEPrnvQghSg8SpgxTtIzEX7yRnqzlE5qzORBPq8gzHCNXJt+lHUXDa+5Gz5QGGiDQIXoRwoEbirOlag8jhKblnbUkTQVvJixJ6EmMUaMNMZDtdSuIuOep\\/+gzyhI6MvGBbkIUGFiX1fduUe8seBBM0kI4uMQJA0jXs5aT43V+q+uV1UZojNJSh4KYN3Sg1V5Ovte9WdVPVB9hP2UijYMiUsdZHfdVujz6CB9j5ExCoXU68LCv7FHGCSU9b3VCOhUkBemtPityCToxW1ofoXGWRgzGBKCaEYhWQuPoZLfFMKN\\/qKI9djS+pQ+9qyCa9qqCBEPNhMYNuAqMVgX5OHrf0kBHVyGntoKUI\\/2ExmDky+UpIEQ7\\/3qyhQhJzGyrh\\/Ax5DpajmyyQukFIr9CaBit\\/YfvpaULhVU2++6XCB9qvZ6XwEvNiuK6lBtixQnz54WqIURnf\\/scLWGAmfWellsBFyIEugkDTTvz99Hqkp7jZnql7OnvE2K13tbnjQmhm2CBrFKpvs9CiNQavH5v4ylhwC2Tdb94uBg0yK6PcDGOibmgt64XZfRIh306TJJ0z\\/TtfofwDu2IXGG14mwZ8V7pZNUEJi5eyza2IuFSG+HBhlZkAZolhiTfYQgRQHy4CwCMunwAWhnTlomE+RGh8oStzjy24svaCGOlXtg\\/v+Db3up3Zi\\/hUg325nWRPcb6HUKDZheFLlZcXDMPhebEM\\/RCyX\\/5faVAONVKaNxiUfBQpsRJ\\/Lcn5pHthLwVkB\\/q+D3C2GRNKFPcmArpKSYMzb5O2NN3JZZTjH+P8BQfJ4rJIF9C9kaYpMceIpRIUv9Fwp0XIxRnkmfCv8PPcEyHl0BmZPWLhAM\\/Tmj6DFFM34ChfE72AlsyOW8i4aY2wun7+rp\\/jXRbrSRCnu\\/SGnJCL5zmsSX\\/sWVym8ePK4SvdRP2fqDnOT+Rdyo5JuVc44SRbM0DMTVSqUeaCJFtgJGjyYTAwz\\/XF9I1L+FOnRpTmJKMHCaEEUKnDkLUhQFXitAEOLdqwDtEYIW\\/SOcipfJydBJGrjONkGQwrzmhFUlloUkBMCML61cITUlCvBhL8Oq8SDJjOcJa2iGaWANtWULzMdDocUsL3Mg1FSYc1vYM8dShG0lfzYjvWxvBksZyGdUJdyJh\\/nxPAULsn8CP0O3LmsEQo1KxFThPSLgnHpgFxMQUSgiy4992LAnuiQkfPcGEP0ZCCDaHrLkaAGMjpGcmfDwR6526NoywFRsNC4AJq0+em\\/Dhs2z2rRDhcNpPb5JJy82VCAfaCYN1h\\/bhtSMQtugyv7gSiwU9IWEvkhAFLM8ZHtko7\\/GJW\\/LURXJAvxyhVwehkdDQmAUFq\\/f3r9MmkTDZt35GwlnaW4gQXeimzQr7Sc71MxKKiw+VlLgeVZnQ00BonKNrtGURE35dnRD\\/Bg5\\/1EVo3I6wUGVhYcHEkxMaRms9vLhSc\\/dhxNiS2qKErboJHxp\\/Dt30yV5gJfYZlhmZLVIj9LQSBpD788Z13CgmsFzf+jgnNtbossYnJzRQ4YSv85I9L+A5jrcaneadNJsbSaopRwg1EBKR5wVWuzdeT+icEAmPFn17\\/mdIxD1v8ejBTUJ078KHSj7D\\/PmqyglDp6T1MyMSlv2rEQbzHx5ehPgkhEannWhqhSpU\\/3bCtD0H+CLlfz0hXewZFaudW4owISoSU+2EtDRT7CmSmRg1QocTtp+GEBc+jeuCx1J\\/gZDWFYkI+IjpTxAayRWWAQzCGuqEOBjyXISt5C1A0FjqbxCygqFRRHtRktB9FkJc4jUuC\\/TPqoQwRJifwKGJ0NglL8q0todyz\\/B5COlGGfGnaP6Nt9RgRZ3TpExoPtszTBtK\\/SVCI3PbqD9BmLSOX40wyOl4ZsK0blGacPdjspyqwoRgKcMTlxSh0UuPI0sQkrrpOBzZL0I4QxV1i9V4lSM01sndohThIFRDES2YB7YaodENDHqxrQclCXEFnEKEHVxslLaiViFCXOvNyy4xlCxZwpShVD7hFJUf5YXiUS1TeUJ6Wbjqa1rFyyxJE4aqvckTTiN103HxVnlCNv+MxzmZBdiTJU\\/YSp7pyCHsRoqN4jAl8CQJhYWPpPKs8jYE8oRGP3GckU2ISl0LYdaPWL3VVJENh\\/ikF6r9pr7HkgKh8ZaUj5JJiIuNxstf3iWy30+k4CkvhIa75ayie0lSITT2CQY1i5BU+WI\\/faSl46XKbdBNgXjB+hsucq3m3CgR0o16JAl3qEIXv0DVHQtHtAAtuza0CwtQW8+qRkjvqxQhiikLealXUjoeSlcyIqMaa8suDt004KrstKBIGB9KpRKiIpDAZraQbeGpUGeDGCah1h6quCxT2IwpeXYtXbHwWxphB\\/mfPE+FTIQobsZGspwERFyeWH73oylUfIbhKqjphLhnFyqXkiWpidlH6aI3VFiMhQsMDmWLykxJuwLyRagH4eWzyYTRuumdlUL1WVG05rxQq\\/c7XIcxR3RVkMqmV6HllSmEyFEW9ieM7VgoLVq2lu8QhCfhZXcBHpNRURDjlVZoViqRMFpslO5YWGQDPzr8Fvw1lbHUnHZw8aSnDH172YTRYqPxHQtV9EaG34K\\/hl6RnHKtRFc6JvKUfKGum0X4GWkoJB5pFQxDGPMfbIaF2pBozkiqACtb7GqrtRA+KxUn3Ecq6n8R16RduDTajtZXZhWXp6hh+\\/ll1xc8Yyi6mCZbvFuMESJDJCSJkQBIqQ38yJ5KgI+lcGnwfNPM1yubUO0hUhMXI0R5uoLjQpKvgVuqZBjZWUH4FRQcyR1LhdK7JB65qJufSIi37uC1s96oK1OymhYdS7X5WArV0XYy7Md09hIOvbjbvUrFq9dLAmELb9fHfTVaOl5xUBcX3Su+zV52XGU\\/dWOX\\/Y\\/v29GyGJ5\\/seXNAX4DQoS4bjp\\/dToVbn5MRs\\/iWApv+pTydrxeEkuU2KaCwUO9gEg4vSMjzl53ulNAgfhRgsiwxuadTi+Ipaftl9OKV6fBUjnn3fe8H8GVRhFHL+bKVLUJOemiBH8N7U0GJBYzFtV0fb2u+T054rrd7O9DxXut0kIlwinWwSkBqA8xpM\\/Idn3xHQvLivbCHg\\/V4e2OpcdSpbSObNdH7XvZen2iOmZsLPWiZ0dng+5bzXcgom6ISsAhXwtinB3eeR8jWy3XpdslHCJ6Jdtz2hXvRcw6WO6vIftW98bjZL6FF4uaU8DKC77TbccufL0Zsmj1bh5PghR+eH\\/C0JHq9EbGUoK\\/tsFjqXmvNs3RWIavNB2TaFVFWx1HRPen5RP7U4RoOTUKhBo\\/2666Elcmrivx5rl\\/389MF6lIQonTDXFliu7KmSsyJhIM28KsHdGN7E8oN4NWVCR9WdigZxxsu12n4Jadne5YWDQqI6VjbCw1mHRrFd+x5pvOoNXrZRCXMH3L49rENnKs2xkmG5GX3CtaXXTzZatsfex8kXkp+0MrYo+E\\/SQ2HSutKRkuFowzF9ONbIrrKexUXlz9DZmXGvU7mkS9Ykem\\/FcViGSgYYN6OwouOzaFUjciXR+qTfgJqs+gFVZWWmhtkpsRqko3lX0sqpFWy2ZEp2s1SHsH\\/BhLRUta1yq4Sgf83z\\/1IO6PLxp1zHBl\\/qmJsFGjRo0aNWrUqFGjRo0aNWrUqFGjRv81\\/R\\/YQGPkLRR2\\/AAAAABJRU5ErkJggg==\",\"company_url\":\"https:\\/\\/www.satnusa.com\\/id\\/\",\"location\":\"Batam - Indonesia\",\"start_date\":\"Jan 2021\",\"end_date\":\"\",\"description\":\"- Led digital transformation initiatives using AI, IoT, mobile apps, and RPA, aligned with strategic goals to enhance efficiency, achieving $318K cost savings with a lean team of 16.\\n- Developed cost-effective hiring approach by onboarding skilled interns from polytechnics, optimizing costs and ensuring high performance.\\n- Spearheaded high-impact projects, including AI visual inspections and smart parking, enhancing operational efficiency by 30%.\\n- Developed the MySatnusa Super App which centralized workflows, advanced digital transformation, resulting in more streamlined processes, enhanced team collaboration, and improved overall productivity.\\n- Utilized Agile methodologies to enhance project delivery and effectively adapt to evolving requirements, which led to a reduction in delivery time by 25% and significantly increased client satisfaction, resulting in a 30% rise in repeat business.\\n- Enhanced team collaboration and accountability by incorporating team-building activities, establishing structured feedback processes, celebrating achievements, and setting clear KPIs, which fostered a positive work environment and improved team performance and productivity.\",\"current\":true,\"gallery_ids\":[]}]', 'about', 'json', '2025-10-31 11:24:49', '2025-11-02 08:26:58'),
(8, 'social_links', '[{\"platform\":\"LinkedIn\",\"url\":\"https:\\/\\/www.linkedin.com\\/in\\/alisadikinma\\/\",\"icon\":\"fab fa-linkedin\"},{\"platform\":\"Youtube\",\"url\":\"https:\\/\\/www.youtube.com\\/@alisadikinma\",\"icon\":\"fab fa-youtube\"},{\"platform\":\"Instagram\",\"url\":\"https:\\/\\/www.instagram.com\\/alisadikinma\\/\",\"icon\":\"fab fa-instagram\"},{\"platform\":\"TikTok\",\"url\":\"https:\\/\\/www.tiktok.com\\/@alisadikinma\",\"icon\":\"fab fa-tiktok\"}]', 'about', 'json', '2025-10-31 11:24:49', '2025-11-02 08:26:58'),
(9, 'site_name', 'Ali Sadikin Ma', 'site', 'text', '2025-10-31 13:06:37', '2025-11-03 00:38:41'),
(10, 'contact_email', 'ali.sadikincom85@gmail.com', 'site', 'text', '2025-10-31 13:06:37', '2025-11-02 08:37:55'),
(11, 'contact_phone', '+6281380163758', 'site', 'text', '2025-10-31 13:06:37', '2025-11-02 08:37:55'),
(12, 'social_media', '[{\"platform\":\"github\",\"url\":\"https:\\/\\/github.com\\/alisadikinma\"},{\"platform\":\"linkedin\",\"url\":\"https:\\/\\/linkedin.com\\/in\\/alisadikin\"},{\"platform\":\"twitter\",\"url\":\"https:\\/\\/twitter.com\\/alisadikin\"},{\"platform\":\"instagram\",\"url\":\"https:\\/\\/instagram.com\\/alisadikin\"}]', 'site', 'json', '2025-10-31 13:06:37', '2025-11-02 07:53:28'),
(13, 'site_description', 'AI Automation Architect specializing in intelligent systems that connect web, mobile, and edge AI technologies. Helping companies automate workflows, optimize operations, and scale innovation through smart, data-driven solutions.', 'site', 'text', '2025-10-31 13:14:54', '2025-11-03 00:38:41'),
(14, 'meta_tags', '[{\"name\":\"description\",\"content\":\"Ali Sadikin Ma is an AI Automation Architect specializing in enterprise-grade web, mobile, and edge AI systems. He helps businesses automate processes, scale operations, and unlock innovation through intelligent technology design.\"},{\"name\":\"keywords\",\"content\":\"AI Automation Architect, AI Developer, Industrial Automation, Edge AI Systems, Workflow Automation, Web Developer, Remote Tech Expert\"},{\"name\":\"author\",\"content\":\"Ali Sadikin Ma\"},{\"name\":\"robots\",\"content\":\"index, follow\"}]', 'site', 'json', '2025-10-31 13:14:54', '2025-11-03 00:38:41'),
(15, 'site_logo', '/uploads/site/1761946525_alisadikin_logo.png', 'site', 'image', '2025-10-31 13:18:14', '2025-11-02 08:37:55'),
(16, 'analytics_code', '', 'site', 'textarea', '2025-10-31 14:31:29', '2025-11-02 07:53:28'),
(18, 'location', 'Batam - Indonesia', 'site', 'text', '2025-11-02 09:05:44', '2025-11-02 09:05:44'),
(19, 'languages', '[\"Bahasa\",\"English\",\"Mandarin\"]', 'about', 'json', '2025-11-02 09:51:59', '2025-11-02 11:14:03'),
(20, 'certifications', '[{\"name\":\"Google Project Management Professional\",\"url\":\"https:\\/\\/www.coursera.org\\/professional-certificates\\/google-project-management\"},{\"name\":\"Oracle Essbase\",\"url\":\"https:\\/\\/www.oracle.com\\/uk\\/analytics\\/essbase.html\"},{\"name\":\"Outskill AI Generalist Fellowship\",\"url\":\"https:\\/\\/www.outskill.com\\/6-month-ai-generalist-fellowship\"}]', 'about', 'json', '2025-11-02 09:51:59', '2025-11-02 09:51:59'),
(21, 'hero_tagline', 'AI Automation Architect & Senior Tech Consultant', 'about', 'text', '2025-11-03 00:28:28', '2025-11-03 00:28:28'),
(22, 'availability_note', 'Available for consulting and freelance projects', 'about', 'text', '2025-11-03 00:28:28', '2025-11-03 00:28:28'),
(23, 'trust_strip', '{\"years_experience\":\"17+\",\"projects_delivered\":\"56+\",\"clients_served\":\"25+\",\"success_rate\":\"95%\"}', 'about', 'json', '2025-11-03 00:28:28', '2025-11-03 00:28:28'),
(24, 'mission', 'Empowering businesses through intelligent automation and cutting-edge AI solutions that drive measurable results', 'about', 'textarea', '2025-11-03 00:28:28', '2025-11-03 00:28:28'),
(25, 'what_i_do', '[{\"title\":\"AI Automation Architecture\",\"description\":\"Design and implement intelligent automation solutions that streamline business processes, reduce operational costs, and improve efficiency using cutting-edge AI technologies.\",\"icon\":\"robot\"},{\"title\":\"Full-Stack Development\",\"description\":\"Build scalable, high-performance web applications using modern frameworks like Laravel, Vue.js, and React. From concept to deployment, delivering robust solutions.\",\"icon\":\"code\"},{\"title\":\"Technical Consulting\",\"description\":\"Provide strategic guidance on digital transformation, system architecture, and technology selection to help businesses achieve their goals effectively.\",\"icon\":\"consulting\"}]', 'about', 'json', '2025-11-03 00:28:28', '2025-11-03 00:28:28'),
(26, 'approach', 'I believe in combining technical excellence with business impact. My approach starts with understanding your unique challenges, then crafting solutions that not only work technically but also deliver measurable ROI. With 17+ years of experience, I\'ve learned that the best solutions are those that balance innovation with practicality, ensuring sustainable long-term success.', 'about', 'textarea', '2025-11-03 00:28:28', '2025-11-03 00:28:28'),
(27, 'collaboration_modes', '[{\"mode\":\"Project-Based\",\"description\":\"Fixed-scope projects with clear deliverables, timelines, and milestones. Perfect for well-defined initiatives with specific outcomes.\",\"icon\":\"project\"},{\"mode\":\"Retainer\",\"description\":\"Ongoing support and development with dedicated hours per month. Ideal for continuous improvement and long-term partnerships.\",\"icon\":\"calendar\"},{\"mode\":\"Consulting\",\"description\":\"Strategic advisory and technical guidance on architecture, technology choices, and digital transformation initiatives.\",\"icon\":\"lightbulb\"}]', 'about', 'json', '2025-11-03 00:28:28', '2025-11-03 00:28:28');

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
(1, 'Ali Sadikin', 'admin@alisadikinma.com', '2025-10-31 10:07:54', '$2y$12$4oI3f/.wNh.k5tt.tGVWcOC3aSGjuI.ONPx5ZrRnEmyf6OrAJl3Ri', NULL, '2025-10-31 10:07:54', '2025-10-31 10:07:54'),
(2, 'Admin', 'admin@portfolio.com', '2025-10-31 10:07:55', '$2y$12$dlAfzqNEdwKmZZwMQHn06uR802ERVcVoLnqCJVDuha6N1WaBNuthe', NULL, '2025-10-31 10:07:55', '2025-10-31 10:07:55');

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
  ADD KEY `projects_sort_order_index` (`sort_order`),
  ADD KEY `projects_domain_index` (`domain`);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `awards`
--
ALTER TABLE `awards`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `galleries`
--
ALTER TABLE `galleries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `gallery_items`
--
ALTER TABLE `gallery_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `newsletters`
--
ALTER TABLE `newsletters`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `page_sections`
--
ALTER TABLE `page_sections`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
