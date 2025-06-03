-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 02, 2025 at 01:43 PM
-- Server version: 10.6.21-MariaDB
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cfpcwjwg_certification_api_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activities`
--

CREATE TABLE `activities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `type` varchar(191) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `block_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(191) NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `content_type` varchar(191) DEFAULT NULL,
  `content_id` bigint(20) UNSIGNED DEFAULT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `learning_objectives` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`learning_objectives`)),
  `conditions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`conditions`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activities`
--

INSERT INTO `activities` (`id`, `title`, `description`, `type`, `order`, `block_id`, `status`, `created_by`, `content_type`, `content_id`, `settings`, `learning_objectives`, `conditions`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'introduction', NULL, 'lesson', 0, 1, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":false,\\\"enableInstructorFeedback\\\":false,\\\"enableQuestions\\\":false,\\\"visible\\\":true,\\\"isRequired\\\":true,\\\"contentItems\\\":[]}\"', '\"{\\\"objectives\\\":[]}\"', NULL, '2025-05-02 08:22:39', '2025-05-28 08:20:53', NULL),
(2, 'Statistiques accidents', NULL, 'lesson', 0, 1, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":false,\\\"enableInstructorFeedback\\\":false,\\\"enableQuestions\\\":false,\\\"visible\\\":true,\\\"isRequired\\\":true,\\\"contentItems\\\":[]}\"', '\"{\\\"objectives\\\":[]}\"', NULL, '2025-05-02 08:23:19', '2025-05-28 08:53:56', NULL),
(3, 'Exercice - Accident 1', NULL, 'quiz', 0, 1, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 08:23:56', '2025-05-02 08:23:56', NULL),
(4, 'Exercice - Accident 2', NULL, 'quiz', 0, 1, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 08:24:29', '2025-05-02 08:24:29', NULL),
(5, 'Situations à risques', NULL, 'lesson', 0, 1, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 08:25:08', '2025-05-02 08:25:08', NULL),
(6, 'Réflexion sur la vidéo', NULL, 'feedback', 0, 1, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 08:28:20', '2025-05-02 08:28:20', NULL),
(7, 'Exercice -Situation à risques', NULL, 'quiz', 0, 1, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 08:29:20', '2025-05-02 08:29:20', NULL),
(8, 'Réglementation', NULL, 'lesson', 0, 2, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 08:30:23', '2025-05-02 08:30:23', NULL),
(9, 'Exercice - Hauteur de travail', NULL, 'quiz', 0, 2, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 08:30:50', '2025-05-02 08:30:50', NULL),
(10, 'Exercice - Utilisation de l\'échelle 1', NULL, 'quiz', 0, 2, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 10:54:33', '2025-05-02 10:54:33', NULL),
(11, 'Exercice - Utilisation de l\'échelle 2', NULL, 'quiz', 0, 2, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 10:55:03', '2025-05-02 10:55:03', NULL),
(12, 'Exercice - Utilisation de l\'échelle 3', NULL, 'quiz', 0, 2, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 10:55:25', '2025-05-02 10:55:25', NULL),
(13, 'Analyses des risques', NULL, 'lesson', 0, 3, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 10:55:53', '2025-05-02 10:55:53', NULL),
(14, 'Exercice - Importance de l\'évaluation des risques', NULL, 'quiz', 0, 3, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 10:56:55', '2025-05-02 10:56:55', NULL),
(15, 'Responsabilités de l\'employeur', NULL, 'lesson', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 10:58:41', '2025-05-02 10:58:41', NULL),
(16, 'Principes généraux de prévention', NULL, 'lesson', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:00:07', '2025-05-02 11:00:07', NULL),
(17, 'Exercice - Principes généraux de prévention', NULL, 'quiz', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:00:39', '2025-05-02 11:00:39', NULL),
(18, 'Protection intégrée', NULL, 'lesson', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:01:05', '2025-05-02 11:01:05', NULL),
(19, 'Exercice - Protection intégrée sur un bâtiment', NULL, 'quiz', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:03:29', '2025-05-02 11:03:29', NULL),
(20, 'Exercice - protection intégré sur silo', NULL, 'quiz', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:03:58', '2025-05-02 11:03:58', NULL),
(21, 'Protection collective', NULL, 'quiz', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:04:56', '2025-05-02 11:04:56', NULL),
(22, 'Protection collective', NULL, 'lesson', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:06:10', '2025-05-02 11:06:10', NULL),
(23, 'Exercice - classification des moyens de protection collective', NULL, 'quiz', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:09:51', '2025-05-02 11:09:51', NULL),
(24, 'Exercice - Généralités sur la protection collective', NULL, 'quiz', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:10:25', '2025-05-02 11:10:25', NULL),
(25, 'Exercice - Garde-corps', NULL, 'quiz', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:10:49', '2025-05-02 11:10:49', NULL),
(26, 'Protection Individuelle', NULL, 'lesson', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:11:33', '2025-05-02 11:11:33', NULL),
(27, 'Exercice - Généralités sur les EPI', NULL, 'quiz', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:13:22', '2025-05-02 11:13:22', NULL),
(28, 'Exercice - Système d\'arrêt de chute', NULL, 'quiz', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:14:57', '2025-05-02 11:14:57', NULL),
(29, 'Exercice - Description du harnais', NULL, 'quiz', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:19:15', '2025-05-02 11:19:15', NULL),
(30, 'Exercice - Longe antichute', NULL, 'quiz', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:19:40', '2025-05-02 11:19:40', NULL),
(31, 'Exercice - Facteur de chute', NULL, 'quiz', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:20:03', '2025-05-02 11:20:03', NULL),
(32, 'Exercice - Sytème antichute', NULL, 'quiz', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:20:29', '2025-05-02 11:20:29', NULL),
(33, 'Exercice - Points d\'accrochage du harnais', NULL, 'quiz', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:21:47', '2025-05-02 11:21:47', NULL),
(34, 'Exercice - Classification des ancrages', NULL, 'quiz', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:22:20', '2025-05-02 11:22:20', NULL),
(35, 'Exercice - Marquage CE des ancrages', NULL, 'quiz', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:22:41', '2025-05-02 11:22:41', NULL),
(36, 'Exercice - Vérification des EPI', NULL, 'lesson', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:23:11', '2025-05-02 11:23:11', NULL),
(37, 'Exercice - Chute en prise dorsale', NULL, 'lesson', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:23:37', '2025-05-02 11:23:37', NULL),
(38, 'Synthèse sur la prévention', NULL, 'lesson', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:24:09', '2025-05-02 11:24:09', NULL),
(39, 'Exercice - A faire et à ne pas faire', NULL, 'quiz', 0, 4, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:24:29', '2025-05-02 11:24:29', NULL),
(40, 'Leçon', NULL, 'lesson', 0, 5, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:24:55', '2025-05-02 11:24:55', NULL),
(41, 'Assessment [1]', NULL, 'quiz', 0, 5, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:25:17', '2025-05-02 11:25:17', NULL),
(42, 'Examen final', NULL, 'quiz', 0, 6, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":90,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:26:32', '2025-05-02 11:26:32', NULL),
(43, 'Evaluation du cours', NULL, 'feedback', 0, 7, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":true,\\\"enableInstructorFeedback\\\":true,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:27:11', '2025-05-02 11:27:11', NULL),
(44, 'Certificate [1]', NULL, 'certificate', 0, 7, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":false,\\\"enableInstructorFeedback\\\":false,\\\"enableQuestions\\\":false,\\\"visible\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-02 11:29:34', '2025-05-02 11:29:34', NULL),
(45, 'Assignment title', 'Assignment desc', 'assignment', 0, 1, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":false,\\\"enableInstructorFeedback\\\":false,\\\"enableQuestions\\\":false,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":true,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-19 19:56:55', '2025-05-19 19:56:55', NULL),
(46, 'Introduction', NULL, 'lesson', 0, 8, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":false,\\\"enableInstructorFeedback\\\":false,\\\"enableQuestions\\\":false,\\\"visible\\\":true,\\\"isRequired\\\":true,\\\"contentItems\\\":[]}\"', '\"{\\\"objectives\\\":[]}\"', NULL, '2025-05-30 16:43:18', '2025-05-30 16:44:04', NULL),
(47, 'Score hign', NULL, 'quiz', 0, 8, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":false,\\\"enableInstructorFeedback\\\":false,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":false,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-30 16:44:44', '2025-05-30 16:44:44', NULL),
(48, 'Final Exam', NULL, 'quiz', 0, 9, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":false,\\\"enableInstructorFeedback\\\":false,\\\"enableQuestions\\\":true,\\\"visible\\\":true,\\\"passingScore\\\":70,\\\"maxAttempts\\\":3,\\\"isGraded\\\":false,\\\"isRequired\\\":true}\"', NULL, NULL, '2025-05-30 16:46:55', '2025-05-30 16:46:55', NULL),
(49, 'Test certificat', NULL, 'certificate', 0, 10, 'active', 2, NULL, NULL, '\"{\\\"showResults\\\":true,\\\"enableDiscussion\\\":false,\\\"enableInstructorFeedback\\\":false,\\\"enableQuestions\\\":false,\\\"visible\\\":true,\\\"isRequired\\\":true,\\\"contentItems\\\":[]}\"', '\"{\\\"objectives\\\":[]}\"', NULL, '2025-05-30 16:48:18', '2025-05-30 17:01:16', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `activity_completions`
--

CREATE TABLE `activity_completions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `enrollment_id` bigint(20) UNSIGNED NOT NULL,
  `activity_id` bigint(20) UNSIGNED NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `score` double DEFAULT NULL,
  `time_spent` int(11) NOT NULL DEFAULT 0,
  `attempts` int(11) NOT NULL DEFAULT 1,
  `status` varchar(191) NOT NULL DEFAULT 'started',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignment_contents`
--

CREATE TABLE `assignment_contents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `activity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(191) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `instructions` text NOT NULL,
  `instruction_format` varchar(191) DEFAULT 'markdown',
  `due_date` timestamp NULL DEFAULT NULL,
  `passing_score` int(11) NOT NULL DEFAULT 70,
  `max_attempts` int(11) DEFAULT NULL,
  `allow_late_submissions` tinyint(1) NOT NULL DEFAULT 0,
  `late_submission_penalty` int(11) NOT NULL DEFAULT 0,
  `enable_feedback` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignment_criteria`
--

CREATE TABLE `assignment_criteria` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `assignment_content_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `points` int(11) NOT NULL DEFAULT 10,
  `order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignment_criterion_scores`
--

CREATE TABLE `assignment_criterion_scores` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `assignment_submission_id` bigint(20) UNSIGNED NOT NULL,
  `assignment_criterion_id` bigint(20) UNSIGNED NOT NULL,
  `score` int(11) NOT NULL,
  `feedback` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignment_submissions`
--

CREATE TABLE `assignment_submissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `assignment_content_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `submission_text` longtext DEFAULT NULL,
  `status` varchar(191) NOT NULL DEFAULT 'draft',
  `score` int(11) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `attempt_number` int(11) NOT NULL DEFAULT 1,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `graded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `graded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignment_submission_files`
--

CREATE TABLE `assignment_submission_files` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `assignment_submission_id` bigint(20) UNSIGNED NOT NULL,
  `file_path` varchar(191) NOT NULL,
  `file_name` varchar(191) NOT NULL,
  `file_type` varchar(191) NOT NULL,
  `file_size` int(11) NOT NULL,
  `is_video` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blocks`
--

CREATE TABLE `blocks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `template_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(191) NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `blocks`
--

INSERT INTO `blocks` (`id`, `title`, `description`, `order`, `template_id`, `status`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Block 1 - GENERALITES', NULL, 1, 1, 'active', 2, '2025-04-30 10:14:05', '2025-04-30 10:14:05', NULL),
(2, 'Block 2 - REGLEMENTATION', NULL, 2, 1, 'active', 2, '2025-04-30 10:14:20', '2025-04-30 10:14:20', NULL),
(3, 'Block 3 - RISQUES', NULL, 3, 1, 'active', 2, '2025-04-30 10:14:29', '2025-04-30 10:14:29', NULL),
(4, 'Block 4 - PREVENTION', NULL, 4, 1, 'active', 2, '2025-04-30 10:15:03', '2025-04-30 10:15:03', NULL),
(5, 'Block 5 - APRES LA CHUTE', NULL, 5, 1, 'active', 2, '2025-04-30 10:15:15', '2025-04-30 10:15:15', NULL),
(6, 'Block 6 - EXAMEN', NULL, 6, 1, 'active', 2, '2025-04-30 10:15:27', '2025-04-30 10:15:27', NULL),
(7, 'Block 7 - EVALUATION', NULL, 7, 1, 'active', 2, '2025-04-30 10:15:40', '2025-04-30 10:15:40', NULL),
(8, 'GENERALITES', NULL, 1, 6, 'active', 2, '2025-05-30 16:42:43', '2025-05-30 16:42:43', NULL),
(9, 'EXAM', NULL, 2, 6, 'active', 2, '2025-05-30 16:42:54', '2025-05-30 16:42:54', NULL),
(10, 'CERTIFICAT', NULL, 3, 6, 'active', 2, '2025-05-30 16:43:02', '2025-05-30 16:43:02', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `brandings`
--

CREATE TABLE `brandings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `environment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `company_name` varchar(191) NOT NULL,
  `logo_path` varchar(191) DEFAULT NULL,
  `favicon_path` varchar(191) DEFAULT NULL,
  `primary_color` varchar(191) DEFAULT NULL,
  `secondary_color` varchar(191) DEFAULT NULL,
  `accent_color` varchar(191) DEFAULT NULL,
  `font_family` varchar(191) DEFAULT NULL,
  `custom_css` text DEFAULT NULL,
  `custom_js` text DEFAULT NULL,
  `custom_domain` varchar(191) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `brandings`
--

INSERT INTO `brandings` (`id`, `user_id`, `environment_id`, `company_name`, `logo_path`, `favicon_path`, `primary_color`, `secondary_color`, `accent_color`, `font_family`, `custom_css`, `custom_js`, `custom_domain`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 2, 1, 'CSL', 'https://res.cloudinary.com/dhzghklvu/image/upload/v1745597610/t10n2qr0j4rocsitdymg.png', 'https://res.cloudinary.com/dhzghklvu/image/upload/v1745597619/qjxj069zhsdsjwexsjsf.png', '#198000', '#ff8300', '#ff7b00', 'Roboto, sans-serif', NULL, NULL, NULL, 1, '2025-04-25 15:55:42', '2025-04-25 16:14:22', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(191) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(191) NOT NULL,
  `owner` varchar(191) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `certificate_contents`
--

CREATE TABLE `certificate_contents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `activity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `template_path` varchar(191) NOT NULL,
  `certificate_template_id` bigint(20) UNSIGNED DEFAULT NULL,
  `fields_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`fields_config`)),
  `completion_criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`completion_criteria`)),
  `auto_issue` tinyint(1) NOT NULL DEFAULT 1,
  `expiry_period` int(11) DEFAULT NULL,
  `expiry_period_unit` varchar(10) NOT NULL DEFAULT 'days',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `certificate_contents`
--

INSERT INTO `certificate_contents` (`id`, `activity_id`, `title`, `description`, `template_path`, `certificate_template_id`, `fields_config`, `completion_criteria`, `auto_issue`, `expiry_period`, `expiry_period_unit`, `metadata`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 49, 'Certificate of Completion', 'Certificate for course completion', 'templates/certificate1-en-example-L8g-27.pdf', 1, NULL, '\"{\\\"type\\\":\\\"all_activities\\\",\\\"required_activities\\\":true,\\\"minimum_score\\\":100,\\\"minimum_time_spent\\\":160}\"', 1, 3, 'years', '{\"certificate_url\":\"https:\\/\\/54.227.222.67\\/certificates\\/certificate_john-doe-preview.pdf?expires=1748797273&signature=3a264613b019c9b06cca9f9e8b247d8ad75bfadee5fae90e965ffce09b3d8acc\",\"preview_url\":\"https:\\/\\/54.227.222.67\\/api\\/certificates\\/preview\\/certificate_john-doe-preview.pdf\",\"access_code\":\"MNMHQ1WK\",\"generated_at\":\"2025-05-30T17:01:13+00:00\"}', 2, '2025-05-30 17:00:56', '2025-05-30 17:01:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `certificate_templates`
--

CREATE TABLE `certificate_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `filename` varchar(191) NOT NULL,
  `file_path` varchar(191) NOT NULL,
  `thumbnail_path` varchar(191) DEFAULT NULL,
  `template_type` varchar(191) NOT NULL DEFAULT 'completion',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `remote_id` varchar(191) DEFAULT NULL COMMENT 'ID of the template in the remote certificate service',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `certificate_templates`
--

INSERT INTO `certificate_templates` (`id`, `name`, `description`, `filename`, `file_path`, `thumbnail_path`, `template_type`, `is_default`, `created_by`, `metadata`, `remote_id`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'certificate1-en-example-L8g-27', NULL, 'certificate1-en-example-L8g-27.pdf', 'templates/certificate1-en-example-L8g-27.pdf', NULL, 'completion', 1, 2, NULL, NULL, '2025-05-30 17:00:27', '2025-05-30 17:00:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(191) NOT NULL,
  `slug` varchar(191) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `template_id` bigint(20) UNSIGNED NOT NULL,
  `environment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(191) NOT NULL DEFAULT 'draft',
  `start_date` timestamp NULL DEFAULT NULL,
  `end_date` timestamp NULL DEFAULT NULL,
  `enrollment_limit` int(11) DEFAULT NULL,
  `is_self_paced` tinyint(1) NOT NULL DEFAULT 1,
  `estimated_duration` int(11) DEFAULT NULL,
  `difficulty_level` varchar(191) DEFAULT NULL,
  `thumbnail_url` varchar(191) DEFAULT NULL,
  `featured_image` varchar(191) DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `meta_title` varchar(191) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` varchar(191) DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `title`, `slug`, `description`, `template_id`, `environment_id`, `created_by`, `status`, `start_date`, `end_date`, `enrollment_limit`, `is_self_paced`, `estimated_duration`, `difficulty_level`, `thumbnail_url`, `featured_image`, `is_featured`, `meta_title`, `meta_description`, `meta_keywords`, `published_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Test Travail en hauteur', 'test-travail-en-hauteur', 'Test travail hauteur', 6, 1, 2, 'published', NULL, NULL, NULL, 1, 5, 'intermediate', 'https://res.cloudinary.com/dhzghklvu/image/upload/v1748624668/wwy46t7ctadtj8kdo7ns.png', 'https://res.cloudinary.com/dhzghklvu/image/upload/v1748624673/of65fnzmqqznz2zhm47u.png', 1, 'Test travail template title', 'Test travail template desc', 'Travail en hauteur, formation en ligne', '2025-05-30 17:04:54', '2025-05-30 17:04:54', '2025-05-30 17:04:54', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `course_sections`
--

CREATE TABLE `course_sections` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_sections`
--

INSERT INTO `course_sections` (`id`, `course_id`, `title`, `description`, `order`, `is_published`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'GENERALITES', NULL, 1, 1, 2, '2025-05-30 17:04:54', '2025-05-30 17:04:54', NULL),
(2, 1, 'EXAM', NULL, 2, 1, 2, '2025-05-30 17:04:54', '2025-05-30 17:04:54', NULL),
(3, 1, 'CERTIFICAT', NULL, 3, 1, 2, '2025-05-30 17:04:54', '2025-05-30 17:04:54', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `course_section_items`
--

CREATE TABLE `course_section_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `course_section_id` bigint(20) UNSIGNED NOT NULL,
  `activity_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(191) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `course_section_items`
--

INSERT INTO `course_section_items` (`id`, `course_section_id`, `activity_id`, `title`, `description`, `order`, `is_published`, `is_required`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 46, 'Introduction', NULL, 0, 1, 1, 2, '2025-05-30 17:04:54', '2025-05-30 17:04:54', NULL),
(2, 1, 47, 'Score hign', NULL, 0, 1, 1, 2, '2025-05-30 17:04:54', '2025-05-30 17:04:54', NULL),
(3, 2, 48, 'Final Exam', NULL, 0, 1, 1, 2, '2025-05-30 17:04:54', '2025-05-30 17:04:54', NULL),
(4, 3, 49, 'Test certificat', NULL, 0, 1, 1, 2, '2025-05-30 17:04:54', '2025-05-30 17:04:54', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `documentation_attachments`
--

CREATE TABLE `documentation_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `documentation_content_id` bigint(20) UNSIGNED NOT NULL,
  `file_path` varchar(191) NOT NULL,
  `file_name` varchar(191) NOT NULL,
  `file_type` varchar(191) NOT NULL,
  `file_size` int(11) NOT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documentation_contents`
--

CREATE TABLE `documentation_contents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `content` longtext NOT NULL,
  `allow_downloads` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `environment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` varchar(191) NOT NULL DEFAULT 'enrolled',
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `progress_percentage` double NOT NULL DEFAULT 0,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `enrolled_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `environments`
--

CREATE TABLE `environments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `primary_domain` varchar(191) NOT NULL,
  `additional_domains` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_domains`)),
  `theme_color` varchar(191) DEFAULT NULL,
  `logo_url` varchar(191) DEFAULT NULL,
  `favicon_url` varchar(191) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `owner_id` bigint(20) UNSIGNED NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `environments`
--

INSERT INTO `environments` (`id`, `name`, `primary_domain`, `additional_domains`, `theme_color`, `logo_url`, `favicon_url`, `is_active`, `owner_id`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'CSL', 'learning.cfpcsl.com', NULL, NULL, NULL, NULL, 1, 2, 'ISO Certified Firm', '2025-04-12 20:51:28', '2025-04-25 15:57:01', NULL),
(2, 'Individual Environment', 'csl-certification.vercel.app', NULL, NULL, NULL, NULL, 1, 3, NULL, '2025-04-12 20:51:28', '2025-04-12 20:51:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `environment_referrals`
--

CREATE TABLE `environment_referrals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `referrer_id` bigint(20) UNSIGNED NOT NULL,
  `environment_id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(191) NOT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL,
  `max_uses` int(11) DEFAULT NULL,
  `uses_count` int(11) NOT NULL DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `environment_user`
--

CREATE TABLE `environment_user` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `environment_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role` varchar(191) DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `environment_email` varchar(191) DEFAULT NULL,
  `environment_password` varchar(191) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `use_environment_credentials` tinyint(1) NOT NULL DEFAULT 0,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `credentials` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`credentials`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_account_setup` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `environment_user`
--

INSERT INTO `environment_user` (`id`, `environment_id`, `user_id`, `role`, `permissions`, `environment_email`, `environment_password`, `email_verified_at`, `use_environment_credentials`, `joined_at`, `is_active`, `credentials`, `created_at`, `updated_at`, `is_account_setup`) VALUES
(1, 1, 4, 'company_learner', NULL, 'learner1@company-env.com', '$2y$12$22.1g2NG.WN85nPRRq26iOA0Cu0ogmrwPWtxu0Tn2H1Lxx.tMCGKG', NULL, 1, '2025-04-12 20:51:29', 1, NULL, '2025-04-12 20:51:29', '2025-04-12 20:51:29', 0),
(2, 2, 4, 'learner', NULL, 'learner1@individual-env.com', '$2y$12$.WyW2AvEAjdNOpk1Rbn4tebfwXY/ro./EtBB5hYzEBcu0qNGVzHcW', NULL, 1, '2025-04-12 20:51:29', 1, NULL, '2025-04-12 20:51:29', '2025-04-12 20:51:29', 0),
(3, 1, 5, 'company_team_member', NULL, 'team-member@company-env.com', '$2y$12$PLvPC9Mb0zUXthKqzsGn9OphXgztvw05PwvcTra/WJbEVyAvPQyJC', NULL, 1, '2025-04-12 20:51:29', 1, NULL, '2025-04-12 20:51:29', '2025-04-12 20:51:29', 0),
(4, 2, 5, 'learner', NULL, 'learner2@individual-env.com', '$2y$12$3WQdEYbtChtrBiDoI1Y85.pxIx9X2LUTb9MSCuumwrMRqk2FEEihW', NULL, 1, '2025-04-12 20:51:29', 1, NULL, '2025-04-12 20:51:29', '2025-04-12 20:51:29', 0);

-- --------------------------------------------------------

--
-- Table structure for table `event_contents`
--

CREATE TABLE `event_contents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` varchar(191) NOT NULL,
  `location` varchar(191) DEFAULT NULL,
  `start_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `timezone` varchar(191) NOT NULL DEFAULT 'UTC',
  `max_participants` int(11) DEFAULT NULL,
  `registration_deadline` timestamp NULL DEFAULT NULL,
  `is_webinar` tinyint(1) NOT NULL DEFAULT 0,
  `webinar_url` varchar(191) DEFAULT NULL,
  `webinar_platform` varchar(191) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_registrations`
--

CREATE TABLE `event_registrations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_content_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(191) NOT NULL DEFAULT 'registered',
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `cancellation_date` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `attendance_confirmed_at` timestamp NULL DEFAULT NULL,
  `attendance_confirmed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_sessions`
--

CREATE TABLE `event_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_content_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `presenter_name` varchar(191) DEFAULT NULL,
  `presenter_bio` text DEFAULT NULL,
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_time` timestamp NULL DEFAULT NULL,
  `location` varchar(191) DEFAULT NULL,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(191) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback_answers`
--

CREATE TABLE `feedback_answers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feedback_submission_id` bigint(20) UNSIGNED NOT NULL,
  `feedback_question_id` bigint(20) UNSIGNED NOT NULL,
  `answer_text` text DEFAULT NULL,
  `answer_value` double DEFAULT NULL,
  `answer_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`answer_options`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback_contents`
--

CREATE TABLE `feedback_contents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `activity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `instruction_format` varchar(191) DEFAULT 'markdown',
  `feedback_type` varchar(191) NOT NULL,
  `allow_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `completion_message` text DEFAULT NULL,
  `resource_files` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`resource_files`)),
  `allow_multiple_submissions` tinyint(1) NOT NULL DEFAULT 0,
  `start_date` timestamp NULL DEFAULT NULL,
  `end_date` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback_questions`
--

CREATE TABLE `feedback_questions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feedback_content_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(191) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` varchar(191) NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `required` tinyint(1) NOT NULL DEFAULT 1,
  `order` int(11) NOT NULL DEFAULT 0,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback_submissions`
--

CREATE TABLE `feedback_submissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `feedback_content_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `submission_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(191) NOT NULL DEFAULT 'submitted',
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `file_type` varchar(191) NOT NULL,
  `file_size` bigint(20) UNSIGNED NOT NULL,
  `file_url` varchar(191) NOT NULL,
  `public_id` varchar(191) NOT NULL,
  `resource_type` varchar(191) NOT NULL,
  `environment_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `issued_certificates`
--

CREATE TABLE `issued_certificates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `certificate_content_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `environment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `course_id` bigint(20) UNSIGNED DEFAULT NULL,
  `certificate_number` varchar(191) NOT NULL,
  `issued_date` timestamp NULL DEFAULT NULL,
  `expiry_date` timestamp NULL DEFAULT NULL,
  `file_path` varchar(191) NOT NULL,
  `status` varchar(191) NOT NULL DEFAULT 'active',
  `revoked_reason` text DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `revoked_by` bigint(20) UNSIGNED DEFAULT NULL,
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(191) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `queue`, `payload`, `attempts`, `reserved_at`, `available_at`, `created_at`) VALUES
(1, 'default', '{\"uuid\":\"7559810e-844e-41a3-9532-4744b7ea4703\",\"displayName\":\"App\\\\Listeners\\\\CreateEnvironmentUserListener\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":3,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":20:{s:5:\\\"class\\\";s:43:\\\"App\\\\Listeners\\\\CreateEnvironmentUserListener\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:36:\\\"App\\\\Events\\\\UserCreatedDuringCheckout\\\":3:{s:4:\\\"user\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:7;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:11:\\\"environment\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:22:\\\"App\\\\Models\\\\Environment\\\";s:2:\\\"id\\\";i:1;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:9:\\\"isNewUser\\\";b:1;}}s:5:\\\"tries\\\";i:3;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\"},\"createdAt\":1748865399,\"delay\":null}', 0, NULL, 1748865400, 1748865400),
(2, 'default', '{\"uuid\":\"0480b49d-4830-473b-99bb-cc70bb329f4b\",\"displayName\":\"App\\\\Listeners\\\\SendOrderConfirmationEmail\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":3,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":20:{s:5:\\\"class\\\";s:40:\\\"App\\\\Listeners\\\\SendOrderConfirmationEmail\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:25:\\\"App\\\\Events\\\\OrderCompleted\\\":1:{s:5:\\\"order\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:16:\\\"App\\\\Models\\\\Order\\\";s:2:\\\"id\\\";i:1;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}}}s:5:\\\"tries\\\";i:3;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\"},\"createdAt\":1748865400,\"delay\":null}', 0, NULL, 1748865400, 1748865400),
(3, 'default', '{\"uuid\":\"10614ef4-eb5b-42e7-b111-aebe420ee035\",\"displayName\":\"App\\\\Listeners\\\\ProcessOrderItems\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":3,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Events\\\\CallQueuedListener\",\"command\":\"O:36:\\\"Illuminate\\\\Events\\\\CallQueuedListener\\\":20:{s:5:\\\"class\\\";s:31:\\\"App\\\\Listeners\\\\ProcessOrderItems\\\";s:6:\\\"method\\\";s:6:\\\"handle\\\";s:4:\\\"data\\\";a:1:{i:0;O:25:\\\"App\\\\Events\\\\OrderCompleted\\\":1:{s:5:\\\"order\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:16:\\\"App\\\\Models\\\\Order\\\";s:2:\\\"id\\\";i:1;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}}}s:5:\\\"tries\\\";i:3;s:13:\\\"maxExceptions\\\";N;s:7:\\\"backoff\\\";N;s:10:\\\"retryUntil\\\";N;s:7:\\\"timeout\\\";N;s:13:\\\"failOnTimeout\\\";b:0;s:17:\\\"shouldBeEncrypted\\\";b:0;s:3:\\\"job\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;}\"},\"createdAt\":1748865400,\"delay\":null}', 0, NULL, 1748865400, 1748865400);

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(191) NOT NULL,
  `name` varchar(191) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lesson_contents`
--

CREATE TABLE `lesson_contents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `activity_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `content` text DEFAULT NULL,
  `format` enum('plain','markdown','html','wysiwyg') NOT NULL DEFAULT 'markdown',
  `estimated_duration` int(11) DEFAULT NULL,
  `resources` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`resources`)),
  `introduction` text DEFAULT NULL,
  `conclusion` text DEFAULT NULL,
  `enable_discussion` tinyint(1) NOT NULL DEFAULT 0,
  `enable_instructor_feedback` tinyint(1) NOT NULL DEFAULT 0,
  `enable_questions` tinyint(1) NOT NULL DEFAULT 0,
  `show_results` tinyint(1) NOT NULL DEFAULT 1,
  `pass_score` int(11) NOT NULL DEFAULT 70,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lesson_contents`
--

INSERT INTO `lesson_contents` (`id`, `activity_id`, `title`, `description`, `content`, `format`, `estimated_duration`, `resources`, `introduction`, `conclusion`, `enable_discussion`, `enable_instructor_feedback`, `enable_questions`, `show_results`, `pass_score`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'Introduction', NULL, '<p style=\"text-align: left\">Selon l\'OIT (Organisation Internationale du Travail), le travail en hauteur reste l’une des principales causes de décès et de blessures graves au travail. Les cas courants incluent les chutes depuis les toits, les échelles et à travers des surfaces fragiles.</p><p style=\"text-align: left\"></p><img class=\"rounded max-w-full h-auto max-h-[500px] object-contain\" style=\"display: block; margin: 1rem auto;\" src=\"https://res.cloudinary.com/dhzghklvu/image/upload/v1748420364/csl-certification/admin/content_image_1748420361.png\" alt=\"image-1.png\"><p style=\"text-align: left\"></p><p style=\"text-align: left\">L\'expression «&nbsp;Travail en hauteur&nbsp;» désigne <strong>un travail dans tout endroit où, en l\'absence de précautions, une personne pourrait chuter sur une distance susceptible de causer des blessures</strong> (par exemple, une chute à travers un toit fragile dans une cage d\'ascenseur non protégée, ou une cage d\'escalier).</p><p style=\"text-align: left\"></p><p style=\"text-align: left\"><a target=\"_blank\" rel=\"noopener noreferrer\" class=\"text-primary underline\" href=\"https://www.ilo.org/topics/labour-administration-and-inspection/resources-library/occupational-safety-and-health-guide-labour-inspectors-and-other/working-height\">https://www.ilo.org/topics/labour-administration-and-inspection/resources-library/occupational-safety-and-health-guide-labour-inspectors-and-other/working-height</a></p><p style=\"text-align: left\"></p><p style=\"text-align: left\">Ce cours aidera à réduire les risques de chute en décrivant différentes situations à risques, en indiquant les mesures réglementaires utiles et en développant les mesures générales de prévention contre les risques de chute de hauteur.</p><p style=\"text-align: left\"></p><p style=\"text-align: left\">La protection contre les chutes sur les échelles, les échafaudages et les plates-formes aériennes sont également des sujets importants abordés dans ce cours.</p><p style=\"text-align: left\"></p><p style=\"text-align: left\">A la fin de ce programme de formation, vous serez en mesure:</p><p style=\"text-align: left\"></p><p style=\"text-align: left\">1.D\'identifier les situations à risques</p><ol start=\"2\"><li><p style=\"text-align: left\">2.De travailler en sécurité en utilisant un harnais de sécurité, des échafaudages ou un appareil de levage,</p></li></ol><ul><li><p style=\"text-align: left\">3.De proposer des mesures de prévention adaptées aux situations de travail<br></p></li></ul><p style=\"text-align: left\">Votre parcours de formation durera 06 heures en moyenne si vous ne l\'interrompez pas. Il vous est possible cependant d\'interrompre à n\'importe quel moment votre cours et le poursuivre ultérieurement.</p><p style=\"text-align: left\"><br>N\'entamez l\'examen final que lorsque vous êtes prêt et disposez du temps nécessaire (01 heure) pour le terminer. <br><br>Une fois lancé, il ne vous sera plus possible d\'interrompre l\'examen.<br><br></p><p style=\"text-align: left\"><strong>Bonne chance à vous!!!</strong></p>', 'wysiwyg', 5, NULL, NULL, NULL, 0, 0, 0, 0, 70, 2, '2025-05-28 08:19:43', '2025-05-28 08:21:17', NULL),
(2, 2, 'Statistiques accidents', NULL, '<p style=\"text-align: left\">Les accidents du travail résultant de chutes de hauteur se traduisent par des blessures, fractures et traumatismes de toutes sortes externes ou internes, dont les conséquences sont très diverses mais qui peuvent être particulièrement graves, voire mortelles.</p><p style=\"text-align: justify\"></p><p style=\"text-align: justify\">En 2019 en France, plus de 10&nbsp;% des accidents du travail sont dus aux chutes de hauteur. Les chutes de hauteur représentent la troisième cause d’accidents du travail occasionnant un arrêt de plus de trois jours avec ou sans incapacité permanente et des journées de travail perdues par incapacité temporaire (selon la classification SEAT utilisée par la Cnam depuis 2013) et la deuxième cause de décès.&nbsp;</p><p style=\"text-align: justify\"></p><p style=\"text-align: justify\">Ces accidents surviennent dans tous les secteurs d’activité, mais c’est dans le BTP et les activités de service II (comprenant le travail temporaire, l’action sociale, les secteurs de la santé, du nettoyage…) que l’on constate la plus forte proportion&nbsp;: le BTP représente près d’un cinquième des arrêts de travail de plus de trois jours (20&nbsp;%), près d’un tiers des cas d’incapacité permanente (28&nbsp;%), et plus de la moitié des décès consécutifs à une chute de hauteur (54&nbsp;%).</p><p style=\"text-align: justify\"></p><p style=\"text-align: justify\">Dans le BTP, c’est le deuxième poste en nombre d’accidents du travail, pour les arrêts avec incapacité et pour le nombre de journées perdues, le premier pour les décès.</p><p style=\"text-align: justify\"></p><p style=\"text-align: justify\">Tous ces accidents sont prévisibles et doivent être prévenus</p>', 'wysiwyg', NULL, NULL, NULL, NULL, 0, 0, 1, 0, 70, 2, '2025-05-28 08:53:44', '2025-05-28 08:53:44', NULL),
(3, 46, 'Test lesson', 'test desc', '<p style=\"text-align: left\">Ceci un test d’un text <strong>riche</strong></p>', 'wysiwyg', NULL, NULL, NULL, NULL, 0, 0, 0, 0, 70, 2, '2025-05-30 16:44:00', '2025-05-30 16:44:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lesson_content_parts`
--

CREATE TABLE `lesson_content_parts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lesson_content_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(191) DEFAULT NULL,
  `content_type` varchar(191) NOT NULL,
  `content` longtext DEFAULT NULL,
  `video_url` varchar(191) DEFAULT NULL,
  `video_provider` varchar(191) DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lesson_discussions`
--

CREATE TABLE `lesson_discussions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lesson_content_id` bigint(20) UNSIGNED NOT NULL,
  `content_part_id` bigint(20) UNSIGNED DEFAULT NULL,
  `question_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_instructor_feedback` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lesson_questions`
--

CREATE TABLE `lesson_questions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lesson_content_id` bigint(20) UNSIGNED NOT NULL,
  `content_part_id` bigint(20) UNSIGNED DEFAULT NULL,
  `question` text NOT NULL,
  `question_type` varchar(191) NOT NULL,
  `is_scorable` tinyint(1) NOT NULL DEFAULT 0,
  `points` int(11) NOT NULL DEFAULT 1,
  `order` int(11) NOT NULL DEFAULT 0,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lesson_question_options`
--

CREATE TABLE `lesson_question_options` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `lesson_question_id` bigint(20) UNSIGNED NOT NULL,
  `option_text` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `feedback` text DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lesson_question_responses`
--

CREATE TABLE `lesson_question_responses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `lesson_question_id` bigint(20) UNSIGNED NOT NULL,
  `lesson_content_id` bigint(20) UNSIGNED NOT NULL,
  `selected_option_id` bigint(20) UNSIGNED DEFAULT NULL,
  `text_response` text DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `points_earned` double NOT NULL DEFAULT 0,
  `attempt_number` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(191) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2025_03_06_003044_add_two_factor_columns_to_users_table', 1),
(5, '2025_03_06_003231_create_personal_access_tokens_table', 1),
(6, '2025_03_06_003231_create_teams_table', 1),
(7, '2025_03_06_003232_create_team_user_table', 1),
(8, '2025_03_06_003233_create_team_invitations_table', 1),
(9, '2025_03_06_013839_add_role_to_users_table', 1),
(10, '2025_03_06_013840_create_templates_table', 1),
(11, '2025_03_06_013841_create_blocks_table', 1),
(12, '2025_03_06_013842_create_activities_table', 1),
(13, '2025_03_06_013843_create_text_contents_table', 1),
(14, '2025_03_06_013844_create_video_contents_table', 1),
(15, '2025_03_06_013845_create_quiz_contents_table', 1),
(16, '2025_03_06_013846_create_quiz_questions_table', 1),
(17, '2025_03_06_013847_create_quiz_question_options_table', 1),
(18, '2025_03_06_013848_create_lesson_contents_table', 1),
(19, '2025_03_06_013849_create_lesson_content_parts_table', 1),
(20, '2025_03_06_013850_create_lesson_questions_table', 1),
(21, '2025_03_06_013851_create_lesson_question_options_table', 1),
(22, '2025_03_06_013852_create_lesson_discussions_table', 1),
(23, '2025_03_06_013853_create_assignment_contents_table', 1),
(24, '2025_03_06_013854_create_assignment_criteria_table', 1),
(25, '2025_03_06_013855_create_assignment_submissions_table', 1),
(26, '2025_03_06_013856_create_assignment_submission_files_table', 1),
(27, '2025_03_06_013857_create_assignment_criterion_scores_table', 1),
(28, '2025_03_06_013858_create_documentation_contents_table', 1),
(29, '2025_03_06_013859_create_documentation_attachments_table', 1),
(30, '2025_03_06_013860_create_event_contents_table', 1),
(31, '2025_03_06_013861_create_event_registrations_table', 1),
(32, '2025_03_06_013862_create_event_sessions_table', 1),
(33, '2025_03_06_013863_create_certificate_contents_table', 1),
(34, '2025_03_06_013864_create_issued_certificates_table', 1),
(35, '2025_03_06_013865_create_feedback_contents_table', 1),
(36, '2025_03_06_013866_create_feedback_questions_table', 1),
(37, '2025_03_06_013867_create_feedback_submissions_table', 1),
(38, '2025_03_06_013868_create_feedback_answers_table', 1),
(39, '2025_03_06_013869_create_courses_table', 1),
(40, '2025_03_06_013870_create_enrollments_table', 1),
(41, '2025_03_06_013871_create_activity_completions_table', 1),
(42, '2025_03_06_013872_create_course_sections_table', 1),
(43, '2025_03_06_013873_create_course_section_items_table', 1),
(44, '2025_03_06_013874_create_products_table', 1),
(45, '2025_03_06_013875_create_product_courses_table', 1),
(46, '2025_03_06_013876_create_orders_table', 1),
(47, '2025_03_06_013877_create_order_items_table', 1),
(48, '2025_03_06_013878_create_referrals_table', 1),
(49, '2025_03_06_013879_create_brandings_table', 1),
(50, '2025_03_06_013880_add_foreign_key_to_issued_certificates_table', 1),
(51, '2025_03_06_013881_add_foreign_key_to_orders_table', 1),
(52, '2025_03_18_182707_create_environments_table', 1),
(53, '2025_03_18_191233_add_environment_id_to_enrollments_table', 1),
(54, '2025_03_18_195000_create_environment_user_table', 1),
(55, '2025_03_18_195100_add_credentials_to_environment_user_table', 1),
(56, '2025_03_18_195142_create_plans_table', 1),
(57, '2025_03_18_195154_create_subscriptions_table', 1),
(58, '2025_03_18_195200_add_is_active_and_credentials_to_environment_user_table', 1),
(59, '2025_03_18_221100_add_company_name_to_users_table', 1),
(60, '2025_03_19_000000_add_environment_id_to_templates_table', 1),
(61, '2025_03_19_000001_add_environment_id_to_courses_table', 1),
(62, '2025_03_19_000002_add_environment_id_to_products_table', 1),
(63, '2025_03_19_000003_add_environment_id_to_teams_table', 1),
(64, '2025_03_19_000004_add_environment_id_to_brandings_table', 1),
(65, '2025_03_19_000005_add_environment_id_to_orders_table', 1),
(66, '2025_03_19_000006_add_environment_id_to_issued_certificates_table', 1),
(67, '2025_04_01_060904_add_fields_to_templates_table', 1),
(68, '2025_04_01_153830_create_payment_gateway_settings_table', 1),
(69, '2025_04_01_181419_create_transactions_table', 1),
(70, '2025_04_02_040040_add_settings_and_objectives_to_activities_table', 1),
(71, '2025_04_02_040041_update_quiz_questions_table', 1),
(72, '2025_04_02_064500_create_files_table', 1),
(73, '2025_04_02_094200_update_lesson_contents_table', 1),
(74, '2025_04_11_155249_add_enrollment_limit_to_courses_table', 1),
(75, '2025_04_11_155536_add_additional_fields_to_courses_table', 1),
(76, '2025_04_12_000001_create_product_categories_table', 1),
(77, '2025_04_12_000002_update_products_table_add_category_and_fields', 1),
(78, '2025_04_12_080746_add_slug_field_to_products', 1),
(79, '2025_04_12_193500_create_product_reviews_table', 1),
(80, '2025_04_24_141646_add_activity_id_to_quiz_contents_table', 2),
(81, '2025_04_25_083300_update_text_contents_table', 2),
(82, '2025_04_25_083400_update_video_contents_table', 2),
(83, '2025_05_02_000001_update_feedback_contents_table', 3),
(84, '2025_05_02_000002_update_feedback_questions_table', 3),
(85, '2025_05_08_000000_create_lesson_question_responses_table', 3),
(86, '2025_05_08_000001_add_pass_score_to_lesson_contents_table', 3),
(87, '2025_05_19_195400_add_instruction_format_to_quiz_contents_table', 4),
(88, '2025_05_19_195401_add_instructions_and_format_to_quiz_questions_table', 4),
(89, '2025_05_19_195402_add_instruction_format_to_assignment_contents_table', 4),
(90, '2025_05_19_195403_add_instruction_format_to_feedback_contents_table', 4),
(91, '2025_05_20_074900_add_activity_id_to_assignment_contents_table', 4),
(92, '2025_05_20_075000_add_title_description_to_assignment_contents', 4),
(93, '2025_05_21_000001_create_third_party_services_table', 4),
(94, '2025_05_21_000002_create_certificate_templates_table', 4),
(95, '2025_05_21_114733_add_activity_id_to_certificate_contents_table', 4),
(96, '2025_05_21_155925_update_certificate_contents_table_add_completion_criteria_and_rename_expiry_days', 4),
(97, '2025_05_21_164209_add_period_unit_to_certificate_contents', 4),
(98, '2025_05_22_145025_add_metadata_to_certificate_contents_table', 4),
(99, '2025_05_26_000000_update_courses_table_for_image_fields', 4),
(100, '2025_05_29_100139_add_phone_number_to_orders_table', 5),
(101, '2025_05_29_189521_create_environment_referrals_table', 5),
(102, '2025_05_30_021650_create_team_members_table', 5),
(103, '2025_05_30_061000_add_is_account_setup_to_environment_users_table', 6),
(104, '2025_05_30_085820_modify_transaction_id_column_in_transactions_table', 7);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `environment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `order_number` varchar(191) NOT NULL,
  `status` varchar(191) NOT NULL DEFAULT 'pending',
  `total_amount` decimal(10,2) NOT NULL,
  `currency` varchar(191) NOT NULL DEFAULT 'USD',
  `payment_method` varchar(191) DEFAULT NULL,
  `payment_id` varchar(191) DEFAULT NULL,
  `billing_name` varchar(191) DEFAULT NULL,
  `billing_email` varchar(191) DEFAULT NULL,
  `phone_number` varchar(191) DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `billing_city` varchar(191) DEFAULT NULL,
  `billing_state` varchar(191) DEFAULT NULL,
  `billing_zip` varchar(191) DEFAULT NULL,
  `billing_country` varchar(191) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `referral_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `environment_id`, `order_number`, `status`, `total_amount`, `currency`, `payment_method`, `payment_id`, `billing_name`, `billing_email`, `phone_number`, `billing_address`, `billing_city`, `billing_state`, `billing_zip`, `billing_country`, `notes`, `referral_id`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 7, 1, 'ORD-NSUFOEB3', 'pending', 50.00, 'USD', '1', NULL, 'Rachel simo', 'sales@cfpcsl.com', '+237694742001', 'DOUALA', 'Douala', 'LT', '12840', 'CM', NULL, NULL, '2025-06-02 11:56:40', '2025-06-02 11:56:40', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `is_subscription` tinyint(1) NOT NULL DEFAULT 0,
  `subscription_id` varchar(191) DEFAULT NULL,
  `subscription_status` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`, `discount`, `total`, `is_subscription`, `subscription_id`, `subscription_status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, 1, 50.00, 0.00, 50.00, 0, NULL, NULL, '2025-06-02 11:56:40', '2025-06-02 11:56:40', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(191) NOT NULL,
  `token` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_gateway_settings`
--

CREATE TABLE `payment_gateway_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `environment_id` bigint(20) UNSIGNED NOT NULL,
  `gateway_name` varchar(191) NOT NULL,
  `code` varchar(191) NOT NULL,
  `description` varchar(191) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `icon` varchar(191) DEFAULT NULL,
  `display_name` varchar(191) DEFAULT NULL,
  `transaction_fee_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `transaction_fee_fixed` decimal(10,2) NOT NULL DEFAULT 0.00,
  `webhook_url` varchar(191) DEFAULT NULL,
  `api_version` varchar(191) DEFAULT NULL,
  `mode` varchar(191) NOT NULL DEFAULT 'sandbox',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by` varchar(191) DEFAULT NULL,
  `updated_by` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_gateway_settings`
--

INSERT INTO `payment_gateway_settings` (`id`, `environment_id`, `gateway_name`, `code`, `description`, `status`, `is_default`, `settings`, `icon`, `display_name`, `transaction_fee_percentage`, `transaction_fee_fixed`, `webhook_url`, `api_version`, `mode`, `sort_order`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'stripe', 'stripe', 'Process payments with Stripe', 1, 0, '\"{\\\"api_key\\\":\\\"sk_test_51PwBeqKtHwwBj4azLlR3dSTW49tD3Ddb8vXX47sICfQMX02UMHx4b6Nq5XbuXvGup7mbGqrsqLkn8aLGzggssXzD00dzoCswDJ\\\",\\\"publishable_key\\\":\\\"pk_test_51PwBeqKtHwwBj4azE5QmEAtLE7USfYLxuUzbypSrJgDfCtdZCA5KDXk3vrMBWFREWaFxvK7qlDPiLCpNNK55U47C00Hpx4mlve\\\",\\\"webhook_secret\\\":null}\"', NULL, NULL, 0.00, 0.00, NULL, NULL, 'sandbox', 0, NULL, NULL, '2025-05-30 17:14:24', '2025-05-30 17:14:24', NULL);

--
-- Triggers `payment_gateway_settings`
--
DELIMITER $$
CREATE TRIGGER `trig_payment_gateway_single_default` BEFORE INSERT ON `payment_gateway_settings` FOR EACH ROW BEGIN
                IF NEW.is_default = 1 THEN
                    UPDATE payment_gateway_settings 
                    SET is_default = 0 
                    WHERE environment_id = NEW.environment_id 
                    AND is_default = 1;
                END IF;
            END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trig_payment_gateway_single_default_update` BEFORE UPDATE ON `payment_gateway_settings` FOR EACH ROW BEGIN
                IF NEW.is_default = 1 AND (OLD.is_default = 0 OR OLD.environment_id != NEW.environment_id) THEN
                    UPDATE payment_gateway_settings 
                    SET is_default = 0 
                    WHERE environment_id = NEW.environment_id 
                    AND id != NEW.id 
                    AND is_default = 1;
                END IF;
            END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(191) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plans`
--

CREATE TABLE `plans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `type` varchar(191) NOT NULL,
  `price_monthly` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_annual` decimal(10,2) NOT NULL DEFAULT 0.00,
  `setup_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features`)),
  `limits` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`limits`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `plans`
--

INSERT INTO `plans` (`id`, `name`, `description`, `type`, `price_monthly`, `price_annual`, `setup_fee`, `features`, `limits`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Look And Feel ILT', 'Standard plan for individual teachers', 'individual_teacher', 0.00, 0.00, 1000.00, '\"{\\\"max_students\\\":100,\\\"max_courses\\\":10,\\\"custom_domain\\\":true,\\\"white_labeling\\\":true,\\\"priority_support\\\":false}\"', NULL, 1, 1, '2025-04-12 20:51:27', '2025-04-12 20:51:27'),
(2, 'Look And Feel BST', 'Standard plan for business customers', 'business', 0.00, 0.00, 1500.00, '\"{\\\"max_students\\\":500,\\\"max_courses\\\":50,\\\"custom_domain\\\":true,\\\"white_labeling\\\":true,\\\"priority_support\\\":true,\\\"dedicated_account_manager\\\":true,\\\"api_access\\\":true}\"', NULL, 1, 0, '2025-04-12 20:51:27', '2025-04-12 20:51:27'),
(3, 'Personal Free', 'Start your teaching journey with our free plan', 'personal_free', 0.00, 0.00, 0.00, '\"{\\\"course_templates\\\":3,\\\"customer_support\\\":\\\"3 days per week\\\",\\\"temporary_domain\\\":true,\\\"payment_gateways\\\":1,\\\"look_and_feel\\\":false,\\\"marketing_features\\\":false,\\\"messaging_features\\\":false,\\\"multiple_enrollments\\\":false}\"', '\"{\\\"team_members\\\":1,\\\"courses\\\":3}\"', 1, 10, '2025-05-30 02:43:13', '2025-05-30 02:43:13'),
(4, 'Personal Plus', 'Enhanced features for growing educators', 'personal_plus', 83.33, 833.33, 0.00, '\"{\\\"course_templates\\\":10,\\\"customer_support\\\":\\\"5 days per week\\\",\\\"custom_domain\\\":true,\\\"payment_gateways\\\":3,\\\"look_and_feel\\\":true,\\\"marketing_features\\\":true,\\\"messaging_features\\\":true,\\\"multiple_enrollments\\\":true}\"', '\"{\\\"team_members\\\":10,\\\"extra_member_cost\\\":15,\\\"courses\\\":20}\"', 1, 20, '2025-05-30 02:43:13', '2025-05-30 02:43:13'),
(5, 'Personal Pro', 'Professional tools for serious educators', 'personal_pro', 250.00, 2500.00, 0.00, '\"{\\\"course_templates\\\":\\\"Unlimited\\\",\\\"customer_support\\\":\\\"7 days per week\\\",\\\"custom_domain\\\":true,\\\"payment_gateways\\\":\\\"Unlimited\\\",\\\"look_and_feel\\\":true,\\\"marketing_features\\\":true,\\\"messaging_features\\\":true,\\\"multiple_enrollments\\\":true,\\\"priority_support\\\":true,\\\"advanced_analytics\\\":true}\"', '\"{\\\"team_members\\\":25,\\\"extra_member_cost\\\":10,\\\"courses\\\":50}\"', 1, 30, '2025-05-30 02:43:13', '2025-05-30 02:43:13'),
(6, 'Look And Feel BST', 'Standard plan for business customers', 'business_legacy', 0.00, 0.00, 1500.00, '\"{\\\"max_students\\\":500,\\\"max_courses\\\":50,\\\"custom_domain\\\":true,\\\"white_labeling\\\":true,\\\"priority_support\\\":true,\\\"dedicated_account_manager\\\":true,\\\"api_access\\\":true}\"', NULL, 0, 0, '2025-05-30 02:43:13', '2025-05-30 02:43:13');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `sku` varchar(191) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) DEFAULT NULL,
  `discount_price` decimal(10,2) DEFAULT NULL,
  `currency` varchar(191) NOT NULL DEFAULT 'USD',
  `is_subscription` tinyint(1) NOT NULL DEFAULT 0,
  `subscription_interval` varchar(191) DEFAULT NULL,
  `subscription_interval_count` int(11) DEFAULT NULL,
  `trial_days` int(11) DEFAULT NULL,
  `status` varchar(191) NOT NULL DEFAULT 'draft',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `thumbnail_path` varchar(191) DEFAULT NULL,
  `meta_title` varchar(191) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` varchar(191) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `environment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `slug`, `sku`, `description`, `price`, `stock_quantity`, `discount_price`, `currency`, `is_subscription`, `subscription_interval`, `subscription_interval_count`, `trial_days`, `status`, `is_featured`, `thumbnail_path`, `meta_title`, `meta_description`, `meta_keywords`, `created_by`, `environment_id`, `category_id`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Test Travail en hauteur Template product', 'test-travail-en-hauteur-template-product-1', 'testTemplatetravailenhauteur', 'Test desc', 99.00, NULL, 50.00, 'USD', 0, NULL, NULL, NULL, 'active', 1, 'https://res.cloudinary.com/dhzghklvu/image/upload/v1748624839/cng5jro8bizicrprudel.png', NULL, NULL, NULL, 2, 1, 1, '2025-05-30 17:07:23', '2025-05-30 17:08:20', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `environment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_categories`
--

INSERT INTO `product_categories` (`id`, `name`, `slug`, `description`, `parent_id`, `is_active`, `display_order`, `environment_id`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'HSE', 'hse', 'Test desc', NULL, 1, 0, 1, 2, '2025-05-30 17:06:02', '2025-05-30 17:06:02', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_courses`
--

CREATE TABLE `product_courses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `environment_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(191) NOT NULL,
  `email` varchar(191) NOT NULL,
  `rating` int(10) UNSIGNED NOT NULL,
  `comment` text NOT NULL,
  `status` varchar(191) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_contents`
--

CREATE TABLE `quiz_contents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `activity_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(191) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `instruction_format` varchar(191) DEFAULT 'markdown',
  `passing_score` int(11) NOT NULL DEFAULT 70,
  `time_limit` int(11) DEFAULT NULL,
  `max_attempts` int(11) DEFAULT NULL,
  `randomize_questions` tinyint(1) NOT NULL DEFAULT 0,
  `show_correct_answers` tinyint(1) NOT NULL DEFAULT 1,
  `questions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`questions`)),
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quiz_contents`
--

INSERT INTO `quiz_contents` (`id`, `activity_id`, `title`, `description`, `instructions`, `instruction_format`, `passing_score`, `time_limit`, `max_attempts`, `randomize_questions`, `show_correct_answers`, `questions`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 47, 'Quiz for Score high', NULL, '<p style=\"text-align: left\">Test instructions</p>', 'wysiwyg', 70, NULL, NULL, 0, 1, NULL, 2, '2025-05-30 16:45:39', '2025-05-30 16:46:17', NULL),
(2, 48, 'Final Exam', '', 'Answer all questions to the best of your ability.', 'markdown', 70, NULL, NULL, 0, 1, NULL, 2, '2025-05-30 16:47:08', '2025-05-30 16:47:08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

CREATE TABLE `quiz_questions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(191) DEFAULT NULL,
  `quiz_content_id` bigint(20) UNSIGNED NOT NULL,
  `question` text NOT NULL,
  `question_text` text DEFAULT NULL,
  `question_type` varchar(191) NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `blanks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`blanks`)),
  `matrix_rows` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`matrix_rows`)),
  `matrix_columns` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`matrix_columns`)),
  `matrix_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`matrix_options`)),
  `explanation` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `instruction_format` varchar(191) DEFAULT 'markdown',
  `points` int(11) NOT NULL DEFAULT 1,
  `is_scorable` tinyint(1) NOT NULL DEFAULT 1,
  `order` int(11) NOT NULL DEFAULT 0,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quiz_questions`
--

INSERT INTO `quiz_questions` (`id`, `title`, `quiz_content_id`, `question`, `question_text`, `question_type`, `options`, `blanks`, `matrix_rows`, `matrix_columns`, `matrix_options`, `explanation`, `instructions`, `instruction_format`, `points`, `is_scorable`, `order`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Test 123', 1, 'Test desc', 'Test desc', 'multiple_choice', '[{\"text\":\"Option 1\",\"is_correct\":true},{\"text\":\"Option 2\",\"is_correct\":false},{\"text\":\"Option 3\",\"is_correct\":false},{\"text\":\"Option 4\",\"is_correct\":false}]', NULL, NULL, NULL, NULL, NULL, NULL, 'markdown', 100, 1, 1, 2, '2025-05-30 16:45:39', '2025-05-30 16:45:39', NULL),
(2, 'Test 123', 2, 'Test desc', 'Test desc', 'multiple_choice', '[{\"text\":\"Option 1\",\"is_correct\":true},{\"text\":\"Option 2\",\"is_correct\":false},{\"text\":\"Option 3\",\"is_correct\":false},{\"text\":\"Option 4\",\"is_correct\":false}]', NULL, NULL, NULL, NULL, NULL, NULL, 'markdown', 100, 1, 0, 2, '2025-05-30 16:47:08', '2025-05-30 16:47:08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `quiz_question_options`
--

CREATE TABLE `quiz_question_options` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `quiz_question_id` bigint(20) UNSIGNED NOT NULL,
  `option_text` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `feedback` text DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `referrer_id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(191) NOT NULL,
  `discount_type` varchar(191) NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `max_uses` int(11) DEFAULT NULL,
  `uses_count` int(11) NOT NULL DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(191) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('T18lOwFtejv6Wm16HSjQLLHieKUM1139GV9F8o67', NULL, '198.54.114.126', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.5735.134 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImlSbzhsNU96R1Z3ZlRJdlYwbVl6R1hnM3RxN3pyV1pzQ0V5cEZPZkQiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1745781715),
('35uhmpW1kH0WdRh6f4ibmYeUMgcdW5TbXa2a1VsP', NULL, '185.247.137.84', 'Mozilla/5.0 (compatible; InternetMeasurement/1.0; +https://internet-measurement.com/)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IkpERUNlbjh5dWpGM09FeVFsR0hvaklRU0hIVXdubGxxbFhzVDNNcFoiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1745716707),
('lzNeH69Nhuq05CVSbjXPF9iUEazkmg8LXFvessCg', NULL, '165.227.178.148', 'Mozilla/5.0 (compatible)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IklBcWpUQkxxeGxzRW56bjhvVkJrRnVVVkZIRzJnb2REdVh2QkJacEkiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1745633388),
('QN4y5diOGXsZSlxArkY1ZKY4QKTkJVqEyeG2yf3a', NULL, '165.227.178.148', 'Mozilla/5.0 (compatible)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImMyeXNQWXhHeVZxc094MGdPODdSMVhlS3p0eGxpTUh2STRRbnZsR20iO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1745633386),
('mPVKMkOOR5rDFwp4jGYqaDbk8Kr0z2Z2RnAfFacr', NULL, '87.236.176.67', 'Mozilla/5.0 (compatible; InternetMeasurement/1.0; +https://internet-measurement.com/)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlEyZTR4YWJjdXM4bkxLNUpZSEpZc1VsVXhjRWxIYlNzUTh1Q0Z2WEMiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1745624925),
('yHHkIGbZsZMpUPMtVnpdqPScWqQAKOEob5TAJaf4', NULL, '87.236.176.163', 'Mozilla/5.0 (compatible; InternetMeasurement/1.0; +https://internet-measurement.com/)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjNMdDNZWXVmUk43VTcxUjk0alljQzJrSlJVY0xTY29CcVFkR0dSQjYiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1745617250),
('Od542rrtagh1l0vRUvdu5ONgAnMLs1OeYFqTdWsX', NULL, '143.198.48.35', 'vercel-screenshot/1.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImtOSTJoM3JONWlKQ2pGUVZoYU9MeTltQWdMbzBHRXpUWmU2RVdoMGMiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1745593576),
('lbM3GFs6POEOuaWGdfHREDo3zZZekrSZnpUB6tm5', NULL, '87.236.176.117', 'Mozilla/5.0 (compatible; InternetMeasurement/1.0; +https://internet-measurement.com/)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImRwdHhWSFd4UThDZEJ3OVRWb21ndXRyUnpITmJjbmVnM2JxQjU1T0EiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1745592770),
('QP9CDrKKpCOEjv2xjWPkXC2StREsIFq6UTt3PKjU', NULL, '147.182.196.130', 'vercel-screenshot/1.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImJ6TWtVQUlsVFBNajNESHJ0ZGkxV2hZcDViMTdyYkJFNHp3RHhBTk4iO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1745593576),
('3hb5bkAN3M8GtARmfVGR24HhO6BDXSWUEaDDSdCh', NULL, '87.236.176.79', 'Mozilla/5.0 (compatible; InternetMeasurement/1.0; +https://internet-measurement.com/)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlVCc256WHlJb0FIakoxcUVCZElDTG15aGRJWGwyVlFidGJJbmxNY1kiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1745491097),
('DeCC1NGZqYxotmLdiZrvR3lIhYU2f29SNR9ROhtV', NULL, '68.178.168.103', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7; rv:129.0) Gecko/20100101 Firefox/129.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InZNMzhkRlhpWmdyV2g2SXJqbEMzaG1oQ2FyU3ZuSjd6ODllZGFRa2QiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1745785235),
('FsDqYH8w4Nh6QPMKpcmKuAuSiHOsG28TVdIpxnnB', NULL, '87.236.176.48', 'Mozilla/5.0 (compatible; InternetMeasurement/1.0; +https://internet-measurement.com/)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InBMZVFIWEdYQXpod09TdDVJN1pmTzhNTWxBR2NDaDU3SlJQRkpsSzgiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1745849937),
('oCaR0O8wOEI9tdgra9S7VKp8Z7JzFTdEeWOpDBem', NULL, '87.236.176.9', 'Mozilla/5.0 (compatible; InternetMeasurement/1.0; +https://internet-measurement.com/)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjFpQkVIUGtVVk00eTFiQUVMSDJmbVJXMzlHVGd1R0ZXWVpwbnBLbk0iO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1745936144),
('J13qxSqw24lVTs5WQOhubukjOOnu5mWcBkPA87Nj', NULL, '199.45.155.109', 'Mozilla/5.0 (compatible; CensysInspect/1.1; +https://about.censys.io/)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImV1TkhVT245ZFdzeEdjMVVucHlFaTJGMW5UUTBWaTZSWkIzVnNDS1YiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1745961144),
('HbZxM4NDuBxTvaPtnWDQSdmxPqL4XOZzl5fnd9EZ', NULL, '162.142.125.192', 'Mozilla/5.0 (compatible; CensysInspect/1.1; +https://about.censys.io/)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InRrWkpHdkt3bWNQZWxjdWdrTHZiTG16T2JUTDlFZ05jOG9HQ3hjRUQiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1745968634),
('5axL5IPS1vUf6q73yfOM82ZKJ7crKE9Afu3po0If', NULL, '87.236.176.101', 'Mozilla/5.0 (compatible; InternetMeasurement/1.0; +https://internet-measurement.com/)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IkQ5aE9LYXAzQm5FTXdRekxNZ2FIbmU0bXAzTmJjRHplQ0JoWjJqaTYiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1745999495),
('veyrvTTgVPiwcchdpzmlHns1Jd7bNZwnDfkRgy5S', NULL, '167.172.37.182', 'Mozilla/5.0 (compatible)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImloaDRVZUcyVzBhQm9FY1IzQ21LTnpVV1JFYzY4SDk3RWN6cHJLaDQiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1746059842),
('nEp3fwKmd4Hb6R426xX6inIMkVbiOwRbkEsZKrmW', NULL, '167.172.37.182', 'Mozilla/5.0 (compatible)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlltMWIxdGJKRm1kc0c1SWpZajJQaWxHbzNJU1RvS1NqQW93VjNEdHgiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1746059842),
('0VFvEzpdPi0cGa0aWdXAimZssGJmPl7MTwJLxukS', NULL, '23.239.27.53', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:128.0) Gecko/20100101 Firefox/128.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImUzQ3h5ZWxWZGk0VXJuRVVSVEdPZVllbjZRSE1qYjkxWXFabDUwMUoiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1746156472),
('e8Nj8gEapsUJdkh0zh8evf0HHnVLbPbk75TJ8Xgm', NULL, '5.253.115.96', 'Go-http-client/1.1', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InhRUEhTNmdzbUdNQUI3TXlZOUZNVEdqVUdObXZXQTJLQ3Rnb2ozQnQiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1746181676),
('xigPfTJAfPgbNlJ09DVxToWMmpkt5dVGbwNubJUe', NULL, '107.174.224.18', 'Mozilla/5.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlRuQmt2UDhrYnNWd2ZGanhBbTI4aGdUTGtqdWFQc3hoejJ0dkdXNnoiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1746231757),
('9tiUdXsoHR9kw5EYvoUNPaa1rndpKOQ58xPecJeW', NULL, '107.174.224.18', 'Mozilla/5.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImRSZTBGUW00SWlUSWo1T0NRRlpDQ3NndnJpSHphMHliYkZ3bWtBa3IiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1746617293),
('tV81Y4TAtUnSn1w0HiJPuXA4ACV4C1CFGeXpzSvO', NULL, '44.247.240.154', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Ikdhbno0SGx4TzF4RlNUMnVRc3J3R3RoMndmTkpZcFdad2JXNUZYQ3kiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1746619136),
('VRc824G4CekvjeQqRWL7CaFboGJCdSTdC2qJTJ3d', NULL, '24.144.83.8', 'vercel-screenshot/1.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InRYMTNFbkZvYUQ5SHFFeUd5am9nTmZFWFRCQ1VqUmx2WGRXVUFMNGYiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1746721428),
('1DWYGARLYhUCM1lDYEOJ2bzERJsJihRuZEqA9pzL', NULL, '143.198.235.224', 'vercel-screenshot/1.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjNSVnlHVERwSmFTWE4zQjJrZ09XeG5nYngxeEp3NldaRjJUQkYwcVoiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1746721428),
('X5DO8DFSNI2Lo4UHS1T5sbdslAKrl4rTfBqgB3fQ', NULL, '135.148.100.196', '', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlM0NTMxUUcyYzByWU9tR1U0UnR1VlAwUGpac2VoWlZxeks1cjZmNFUiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1746789857),
('lsRobD2gehFjYafgExYRzwp67TlCWpyVrzv3K2ZO', NULL, '143.198.29.134', 'Mozilla/5.0 (X11; Linux x86_64; rv:136.0) Gecko/20100101 Firefox/136.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjY3NnVudlIxSTlPc1ZiUDg5U2lCMmk5SW15aURtZkk1NW5ncmdxY2IiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1746880279),
('xVb9Q7irQhUMRDKO3b3SWjran7rLYleRZd2VszLb', NULL, '143.198.29.134', 'Mozilla/5.0 (X11; Linux x86_64; rv:136.0) Gecko/20100101 Firefox/136.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IktoYWdMOVlkRFVld1p5bXlVbmZDNjloTGU5aGJ5ek5MYzRWUFo3bnAiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1746880279),
('xg2hOmvonciTuIsp7FjEk08wmmWiL2Ps5c2yqdyZ', NULL, '54.194.211.111', 'Mozilla/5.0 (compatible; NetcraftSurveyAgent/1.0; +info@netcraft.com)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InRYNmVxeWg1SVNNWVZxZHdqZ0tvaHdVSUFVdjA2eDR5YjZjYnlXbmciO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1746935855),
('3NUAmxQWgSvAQIuogLb4DI56Tpwpryv0nmXkV1M8', NULL, '34.141.151.163', 'Scrapy/2.12.0 (+https://scrapy.org)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IkpZb0F1ODAwZ3YyWkJJQldtalB5dnUzdENjRWtUQWZ3TTk4bEprRjciO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1746941168),
('sgD7wDrsOiNJR3BGD7yakREyFNvF0bADb8nLUmyP', NULL, '34.147.88.45', 'Scrapy/2.12.0 (+https://scrapy.org)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Im5PcXRNT1RtcnFLUHBQWGc2dzZsMVk2MjhBRGdlb05ucUtQWE1ERVoiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1746941169),
('pGxrJtojwyemCLmtGscVvF1NZdji8xiMmOMEOzfj', NULL, '54.209.165.177', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImhJUHhXN0NKd3hmUFZVc1QzMHlKTWpoWEJvU0Q1WVBLQXdWOXVkc1EiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1746942055),
('s4Kfe6QVYDVGOcSxLjPkn4eHSRwuScbJic5giZ3R', NULL, '54.154.233.57', 'Mozilla/5.0 (compatible; NetcraftSurveyAgent/1.0; +info@netcraft.com)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InlMcHc2aTN2OXd3Wk1icVpId3h1eW5DNnozdnNDRzJVYTYwMklua0IiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1746955001),
('lrO48E5OmyL35sc1Tssmt218bXHJ3ncFOD9K9DjJ', NULL, '149.102.245.160', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Ikh6WUJRWmE0ZGdTdEVTcnU4dFpwU3hHNHV2UWlXMDFvTWVZNHJZY3IiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1746970846),
('6V0kk6el2AbtPw6ZNBsTMsMl8gQfwBKXYw06r7QP', NULL, '94.102.49.206', 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InhvNkJFRk5HNXNPTG9VQ2RzWTN4WFh2THpZeWROcDFReW50U0xBOFoiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1746970916),
('JotE3vVzmSTFuCd9L5VQxnn47dQXLxfItrPDRkGu', NULL, '80.82.70.198', 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlViTTRMWVhiWUszY1d4cmVad0xFa3YyMTVjMVJPbVhiaEVmWDQ5cEEiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1746970917),
('TmF0XNFOShLaiZfhsIMyC5FjSSZusjCdYulBPZ6c', NULL, '188.126.89.37', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.89 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Inh2SUFpMnRJbVRUdGlJcFA1cXpwZWtVWmhrN0JIZDVIVm5GVFg4amEiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1746970929),
('UOFgAXXk14OqhxdnvIfDVLLEAp6AX29bkywNQzHM', NULL, '104.232.220.118', 'Go-http-client/2.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImEydzNoYXJ3Q28yaG9KY3JmUDFoMzRwTnhCVUIzQWJLbnFXZ0x3RUgiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1746973361),
('FMgNpdbjKdChChVRj2kUmYKsqplbsyjgFhFrVzrB', NULL, '40.80.158.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Ild0eFpwSGdpdkljUEZSaEJwWXRYTjJTSFJXbVhNNVdDVUdoTG1YeEgiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1746990646),
('36mZqQhCtmJ2p5W3hRbSjPk2rOxPCbPfGv89JGMh', NULL, '35.180.234.191', 'Mozilla/5.0 (Linux; Android 7.0; SM-G892A Build/NRD90M; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/60.0.3112.107 Mobile Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImdBVjRPdjRuSDVUY2VoaEd0QlFzSmJRUk9uOERkNWVPNnBybmRtd2MiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1746992055),
('stxuuATqgDao8KXc5ebQRuKBiRoXNYWXYxAV5Azg', NULL, '107.174.224.18', 'Mozilla/5.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Imx6ZExFNmZJcjBJVDJ6UzhmNmhZbDBxQ0dSeXJsZXpJTGk4WmcxZ3kiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747100629),
('mxog5xCFT6eKuRh1L0OqfNpO28HBfi8xq3Thp5bg', NULL, '89.248.171.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/104.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InIzUHR4ZFJmN1k2RXpoT2VjMUttYkVaTXBSV1lCQzdJck83ZmRhb3ciO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747137163),
('Y1MQHgjHSGohDfh4lFRllCawPXsKsomq4qNqlaag', NULL, '45.10.164.226', 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlpibkxFdzl4TG92SkFXbnFjc2hDNUNCc3ZLQkZEb1FBb3NvdVdoYlAiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747163087),
('8FaBkZcWQIrgrfEGwMeCTENVPg5wp8HKi2mx2yg9', NULL, '45.10.164.226', 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImlGRlBKSGFXQ0Y4dU5JbHlINThFQzdod0ZYUHBiSWd6VVhXcDdIbmkiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747163092),
('MjfgJ3mR0IuKSydzNnXBbsvcFhsEKzyECx9Mbk63', NULL, '174.138.83.53', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Edge/120.0.0.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Im5UQTc2bnBvSlM4cElFbWxibkIyWXoyNW5jRHdhRktxV2ZKQnBJVnkiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747297187),
('cncnD6NcvaTyPn2cLOzNZOixyUyGotosiG3R6UB2', NULL, '174.138.83.53', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlRFUDE1SUR1WmR4WXZGUXRjcEl6TU82eW5WZWxoYkU2bk41UXJKMjciO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1747297187),
('lAHTOssY8OXv327ohhr6zKpjg7roXSS9bZdDzVMW', NULL, '161.35.141.206', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlJzWXdkdDgxdWhSU3FqdG05UkFaOVZwYm0xdXluVFFxaHZEUVpVOE4iO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747311914),
('1aQfWQKOPqLQVcdB4w8vejIhGahYo5bV0B7iQDRz', NULL, '161.35.141.206', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjNDMlVGMVJlSHBaYVZuTTRXTXdqVkRFUDIya0t5VlNqREo4Qkk1aFIiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1747311914),
('5YrhGtu3LLRjXxgG2lV2oDTMgYWrdvUI3BxMFzZb', NULL, '51.81.46.212', '', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Im52dU9TMGg3WmpIaTM1dmFxR1d6c2pMRTZvcHdRUDU1YjlyVG82MHMiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747319148),
('TKi1EjG9R1TwFrPJUgAJ7U9M0E4eLgSE3XBhGVnq', NULL, '86.252.6.105', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlVJYjBFdktLZTRBeDRGbGFYWEZ6NkpDSm9RT1ZaSHlRWG4xbTlZaTQiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747323777),
('EdhBVcIQQqrKP88Pla4Y2oVzooNfjKHlzKENxp3L', NULL, '86.252.6.105', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IkxNZXVSbWZ5dWJ6RWpWbWxMZGpnS0VoWDVxWmxWa2pVSUZwOTFueUMiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747323873),
('sMxfxadAWHoH17SzG6tO6Ux78TtjzlzjdWCUmsRi', NULL, '44.205.29.107', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlV6RUFVcWNkeXAyTnpMVXJTck9OWUpNWHRWVmF5eGVRNm1uSW9tRmgiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747369835),
('0ExcwLOdLOm8gW2c1HndWbSnUu0HNum8kH9rm51D', NULL, '44.205.29.107', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IktrS04yNXkzb2ZTSEJKZzJFZFJaUEJ4RXQyUVB2ckg1M3RuVXFzc3MiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747370224),
('t2auqUssVNYexv7IAnAYnJpAGfjCOYAS3S51viVZ', NULL, '86.252.6.105', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InVFNEdocGxhSWZCQ0lmZXlEMVRRZWYxY0tqUWR6UVRkZ2FUSFFZbnciO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747385480),
('5CSbncaFzUGnjCcrOsO0WgqsfsbIvTKJg5W9bRZL', NULL, '86.252.6.105', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjhDZll5Z0xiUlJhS3pTdENNckVENzRSOHdYR081czN3bGhKNzZidTEiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747385480),
('xAi7eBNOvcFGjE7kU84G83B4pZVyE2V4RV9yG9PV', NULL, '185.246.211.78', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Im1Kd3BRYkl4VGk2ODBJV3JuT2hoRk1JTEVDbXpSMDVhWTd6R3VWV1ciO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747403263),
('NufnmKRj68ai6XwMlJeGDBzloWjoMkrPbXIy9SLf', NULL, '94.102.49.206', 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjBRMGdVMFF5VFNCcFRxWkJXdlpBZGw1eTJtOWxUWTV0RGc4MEZSenEiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747403319),
('Si17Y2W3GVcKPP2DOZ3ZR7oIshP8CUoWOdIZjmwR', NULL, '93.174.93.114', 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImFQMmMzRlNrSUlZM08xZHBQY3RLZWppQTF2SHk0Y3VGc0VWNW9XZGkiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747403325),
('auDPgQiaGwMFNRF9xnw16kfxycKKVrUnnXwcv7h0', NULL, '212.143.94.254', 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.5563.65 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImFDUGRqaXRMcEIwWWNNT3pwV0VPZGFtWURGTzFCUWE1aEoweDNNeVoiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747403368),
('NU4pzei4fVg8H5JOKmCaYisqGzFDzcmuz3hhnAnK', NULL, '212.143.94.254', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/122.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InhZdzREYmE0THRETjdqZk41Y2VtUlJOc0xEQjV6WVBJcDZTdjdtWmEiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747403369),
('ChOyVpYTXFLB47avXF8AYhUqGDFGiY1UB73CiIuz', NULL, '5.181.170.91', 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InZ3amxtd0J6eE9XR1Y0WDNjTDNjdHp0S3dvNlNtakZ0Z3lKYlZueWsiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747414665),
('40ZX7ojZPl9olvB4uGe7iTpF0cLikVnAy6rRVx31', NULL, '5.181.170.91', 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlpGazdzSjliejJKNHEzNndaMzY3T3BPUmMybDdONmZXMW5iNXJCNTEiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747414668),
('lsKgrnTBi2AxjwSk3dTz2H6WgBmastgvMgiFfPOr', NULL, '205.169.39.54', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.5938.132 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlMzb0R0SzlEQmk2czhrWHA1SUhXZHFJaWx6ajNUU29Va0xrcXE4VlEiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1747423600),
('SphFSI8g5AWdKvpFzGUzNy96SqcML4YNO0QyMS5r', NULL, '142.93.129.190', '', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InBodG5xQno5TERPWFRiVzRiZ3J3bnNyV2szM3QyWWYzYkxRVEZTZE4iO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747423603),
('TyfQuGmv1ucdSVmghhodOLhsOHKFNIXXTulM3QNR', NULL, '188.166.108.93', '', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InY1bEJmVml1a1BBRnZIbFhlc3hGTnZWN1hZT1d5RkttazJMMnlKalEiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747423604),
('MdC42hldsdKao7fF3pPXbkVNjW9eHe7s9DCxBHPb', NULL, '206.189.2.13', '', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjdCNk9zSUI4MWRxWmxWY3VGOUJDTFU2YUJBNVROcml4VnEwYWVzMTEiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747423604),
('dBIyt11vSKTPqJ7Eo0A3PnAatmDNEqpbRaJHwAwD', NULL, '206.189.2.13', 'Mozilla/5.0 (Linux; Android 6.0; HTC One M9 Build/MRA318676) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.3118.98 Mobile Safari/537.3', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InA5MW5WeE9UUGZlVk96NkczcE5FejZEQmRKeUFRamhzYzEwNkhjYnoiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747423604),
('AKojTJnfwA94KQ5V75c07RocxcOptfZark0czwQ3', NULL, '142.93.129.190', 'Mozilla/5.0 (Linux; Android 6.0; HTC One M9 Build/MRA318676) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.3118.98 Mobile Safari/537.3', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Ik9odHlyTjZqU2Vud2Y0bTZQbXpWSGcxMHBrbjV1a24wdkM2QWlQa3EiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747423606),
('LCMVYbaFMgP8uQ4jhswEwQuIXiazEa4OeFxPmLat', NULL, '188.166.108.93', 'Mozilla/5.0 (Linux; Android 6.0; HTC One M9 Build/MRA318676) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.3118.98 Mobile Safari/537.3', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImlZM1BFdk94NFJpVzdZM3RiMk9tbjJIbE5INlBoSFBVRFFDMmhCRHIiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747423606),
('qHRxDL0QSpRECbfB7iaBogI5rNnjAImBL2r1pGPe', NULL, '206.189.2.13', 'Go-http-client/1.1', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Im5oVkF4a01aUktRRDViQzU1eGpUTVBRbnBSSDFMWjZRYTYxVjB0dnEiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjczOiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20vP3Jlc3Rfcm91dGU9JTJGd3AlMkZ2MiUyRnVzZXJzJTJGIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747423609),
('cVBnwzayRK5uqsbkCOPWkB3uMxCplYAubIsMmoev', NULL, '142.93.129.190', 'Go-http-client/1.1', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImgxU1pnU09hQXI4ZDVCZzFUaU4zSEpTWWVqZFFHbTNHZ0o1WjZTWTkiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjcwOiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20vP3Jlc3Rfcm91dGU9JTJGd3AlMkZ2MiUyRnVzZXJzJTJGIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747423613),
('ETBuzCvTdOfTbBEqlBlxKC57C2KZ6o6FgT8WhIed', NULL, '188.166.108.93', 'Go-http-client/1.1', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Inp3akZPdnBRNnJUY01wbnJ0eGZWVTR4T3JPTFB3TFhveUV2R0hrSVoiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjc0OiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tLz9yZXN0X3JvdXRlPSUyRndwJTJGdjIlMkZ1c2VycyUyRiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1747423613),
('I3bYSNjwc1aJ2s9BkpPdt6w374y6CM4diJzUShrt', NULL, '205.169.39.70', 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImhOYzFMY2hqSGw4VmVFYVBOTnBOZmZmeEE5MmVndUFyOEZWTHVoUEIiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1747423625),
('8iU7k32l018g28sBpOeyRyJFt6AM8Qxssto4Pcti', NULL, '205.169.39.70', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.79 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImphYmRhdFZza3Fsb1lRZThPUXp2VWlWUUZzMnNXVXJYNjZhS2I3MXYiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1747423630),
('d38kjrkRCl2AtcOiS1Rv5SOilUFqEiIsHM5lQh0M', NULL, '34.72.176.129', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/125.0.6422.60 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlM2UDBPNDR5TnNXM1VPSWtZcDJoT1QxZ2d1QmZmeXcxUkhScmZQR24iO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1747423652),
('2VU6mZnt8xWXzxzHvVkktEortviy0URI1NJw5kIj', NULL, '34.123.170.104', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/125.0.6422.60 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Im1sR05vS1hlRXhnYXI1SWRSWVl0ZXFNZm13VkwydWdydFhWQ1VpcXEiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1747423791),
('xOyHiNGMhzWFwpHYDZjFONFfdHqrhpdJuSrQdnMH', NULL, '34.123.170.104', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/125.0.6422.60 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjRZRG5oQ21pTUxkbXRzZTROdUg0MmM2VjhyRWRhaGRvZk5QMEt6V1giO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1747423838),
('PymacasiizHbr4huncMiSYTfWTykHx3mfaB1Lr4B', NULL, '154.28.229.99', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Im5LS2hlUTVsSG1nZmc1ejg1eGNyZ28ydVRSZGt1ZVpVZ3k3VjkyWDMiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747429105),
('PEL5scGaFYB8rOZ8WvvBbC3gbZkjwUpizUuqX9d3', NULL, '154.28.229.99', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlpmTVRTMXFFVDc4SmVxUVdkVmdLemxOc29COUNGdHlaNGE1UlIxdFUiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747429122),
('BeEt7QQHG5TFNcPUuWTQOqCSRkiDkxmsmQuol0K2', NULL, '52.247.239.25', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlpNaHpnWGdLQmVnRFlWR2JpYXdzVDBWVkt5Rnl4Tkt4cGwzb1ZFUTQiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747431100),
('q6M4WXoiWa2mJQjlE8V7J8A3NgG8mw08mdYu3cy1', NULL, '154.28.229.52', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjIzaTBCRTNMZXE2M0UzNXQzWU9pYVd4QUFHcUxNbzNteUh3cnlYNjEiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747432771),
('jcQd2sn9mKR3vALtIVM4AxdxDAqWfJ7y74xHTRdZ', NULL, '154.28.229.82', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlBCdmpBNVNKbG4zakhkS0VJblBnbExiTVN5cE5iOU1ocGNqejFsSHgiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747432772),
('wZj9mX6VEo0wAxrY7u7yJYtpj8LfeLk3FCDenTlW', NULL, '154.28.229.154', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlprU3N1QzFiZzlTd01QeEdGajlvTVJ0NGpvOEhqMHc2TTU4YTBjcTkiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747445411),
('8EIekD6P5I8W7x598COz36NKFIqh3wh4AI6985gz', NULL, '154.28.229.13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IllUMEVRV3NGeEtzbTJ0TWxIMTNaSThLWU1BTVA2dm5nSm50T0lPbm0iO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747445411),
('oaycKJ2HCj2OXh8EqOqzM5Gkzc1z6oM4IBxS7rCW', NULL, '133.242.174.119', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Ik5TV2NRSnRGNXpjSjgwRkl3OTR5UFR3ZDV3enhDQTNCQ0g5UTNIUWsiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747446248),
('6XLkSCsZAAnBoeUqxh76cwkL12kDqaYD8Q5rk15h', NULL, '133.242.174.119', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlczVzloc1lXVzZVTFcwUVhUelpNZks0TlNuNTJVZjBuU3J5N1pUSXkiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747468552),
('OIqDyaLuELfy6wF7xZKnUlJ3ONeff1BAJKVtZB6P', NULL, '5.135.58.196', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:134.0) Gecko/20100101 Firefox/134.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlJuVUdWZEN1b1NFWVJPYlFkeXVFS0ZDdzEyZEZZT0J5YlVITGFjdU8iO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747471394),
('fxPydn1QTuDHj3B0kj16Y15rBMBIFW9eccapvaO8', NULL, '5.135.58.198', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:134.0) Gecko/20100101 Firefox/134.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Ik43MkRLZWFmZWxhbkZtajg3NGo1cXk1ZVJ1SnBzSWJVb2xrUlQ0MlEiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1747471686),
('7Dlr6dOJGqmwIx5Vo17Bjk9mmhviArFpoD51Kw4f', NULL, '5.135.58.195', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:134.0) Gecko/20100101 Firefox/134.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InRhdHFNbktybERxdVBFVUlydXFvcHhhSXNhYXRUelNLTVBZUjJpc0EiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747471763),
('zEiz7YB0odDPsQQ5ymaEFFy8fuUKkC05VNQwJsFC', NULL, '5.135.58.206', 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:134.0) Gecko/20100101 Firefox/134.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IkRhaFJsT0RVWVRBOGxWaXgzM3BEYVdjWFVlbnFTdVBWdUc2U2Z3TEciO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747471808),
('qPmkK4eFlLaJrekGDUeu48edyYQ9CypG03aj9hqb', NULL, '54.198.59.153', 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjlaV3F3UzhNbTRORUg2aGswUVpJR0s1Y0ZwM1NPUWFyR0gxOFNDVU4iO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747481985),
('95iumxUbHnF6jWadgYiT3wNTq5inNWkPrEGJZe5D', NULL, '54.198.59.153', 'Mozilla/5.0 (Linux; Android 4.0.4; BNTV400 Build/IMM76L) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.111 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InIwb2JGZUpVTDhyZFNxcWRsUERRNjlQVEs4SXJhakRmaFhNTmxRV1YiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747481985),
('v2aLkpWi4bUhrgixgFxPquHgyhp4tYqiT8V3Skcj', NULL, '107.174.224.18', 'Mozilla/5.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImxLZ1BDdHBTV1AwTldQVjhVdDhkMGY2VWRlWGhNVnM5T2lKdEdVZXYiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1747506439),
('ozWVJLYd6vafqj4CSokM2LpodbiU5dpCBr33ScH8', NULL, '146.70.119.51', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlA4MWg3c2VveVdmWE5tc1FmZDVWaWFsZk5nOGkySWs3dEpzVEpsQ0IiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747512037),
('9DRsCNAUhi6EeAqKjFKpOokPdldq004j3vFpnzZp', NULL, '209.38.4.240', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36 Edg/127.0.0.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjkwOHdpa2ZHN01KVG5rYm1ocTIycWFMWERDM2VhUkU5dmhWSzFxWEciO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747526667),
('rjtS1pv7OtLFVPya4hZUjOR5a3ZNAXwyYbgS69ZA', NULL, '82.64.198.149', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImJmVjRueXVTaHF1dTlsMHkzckZ2YVJNOHR6TG1QemVKTmplY3BRNk8iO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747526667),
('rjguo404dRRuIaypK22ExDohG1cwtpASm2OqLf1S', NULL, '82.64.198.149', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1.1 Mobile/15E148 Safari/604.1', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InF3QUl3WUhOYTZMMUxqZGdtQ3czSWFVVjJKNjdwelJpWUtTaXVFQTUiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747526745),
('1eBzLKRaUcBMd8zqGgRLk7EAA47ax7gVqKIWFbMV', NULL, '104.152.52.73', 'curl/7.61.1', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlkxZ1BEQVdrSXQyMUExVGtQU210WFhoVXdkSTlXQ3gzSGJhNW5KcGEiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747551890),
('Fo10IGLBGQkdFJENJU7yFlgqq4XxU9ToZc6MShAH', NULL, '104.152.52.73', 'curl/7.61.1', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImRoVEFvc0pCMnJCaXYyNzdramdsZ3djSjA5ajVISzhSYkZ4aXNWeXYiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747573396),
('JGeubIadWsyKDSn42HRB9BP1lbu7Zk5aAguIAaip', NULL, '154.28.229.15', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Im9IYVc4N0g4WEIxZnR0ZGhUZHpzZXZldDV3Nmd3MDZnN0FlVjkxb3ciO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747714807),
('6t1ZT3ZTccfFXVtUeJZJkQz8TELlMCF0NLc6pecE', NULL, '199.45.155.92', 'Mozilla/5.0 (compatible; CensysInspect/1.1; +https://about.censys.io/)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImJRQW80YlhrbHZicERvSFFjaGd2a2NQSkV5UnpGQlR6R3ZyenBPcHkiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1747792405),
('V8QBB86zjXQxDFNtXMTlaTDXxmRTPIuDcrPSMeAo', NULL, '89.248.171.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/104.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IkNwaWpOeUFROTd2U2lkaVB0WlJ5dGVEZktZWmNzOHhRSDNIbFdUbloiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747809385),
('YqxZi0X7N9F0fhTSfKQwxEA7fgZEG1OfYpc2UbPv', NULL, '13.212.87.0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IkFqbENlQ1pLcU04bkVYV00zbDYwWUJUNnh5bDNycHlYbzBGYnBDQmMiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1747941947),
('4kevtU8f7uCKb1iwIXApPyhzgA4LK6Vf2z2nJMbg', NULL, '3.208.116.168', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Imw1TWh6TUdDdVdSRE1waTJwclBuUlBtdndFcUduT2o2c1NIa1M5OG8iO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747977205);
INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('qdlAagLDFgZaQ3jn4TEE7CySeNcmbUZPP1yTSHeJ', NULL, '3.208.116.168', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImRodjJjd29ESW5iOHlObEtlYWJjV2hHUUNtMmtVcGJLbWw2RkJ4bUEiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1747977596),
('wjSCtjRvHH014plUV3J0O2hqH8IeOPp4T9Auy1iC', NULL, '172.245.241.123', 'Mozilla/5.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlVYYXdVbUZBTUIxRWJ1THlLaHI5NFVVTjVoNHdpUHJ5SFJnY2x6RDAiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1747979281),
('STWN2kg4KvlFvfMmr4FG2WOikRHtsl3k4eOJKDt2', NULL, '159.89.179.123', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjBnUkVoWlV0MU15bE8xb2dPN2poNmJOR1c0dFpEVXZaVXA3OUVvUVgiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1747983942),
('uerhca02SSs6onLA4l1hIz8eL1D2mTPsQ5NHKZTd', NULL, '159.89.179.123', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjAzOUNPT1ZHN1ZCV1BYU1NxbkJoeUVlU045cUhjQ0FJbTdmNXZma3ciO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747983942),
('0aOgOQ7crRacayjZJk2xCk1wNh1wSh7RheFX48mq', NULL, '3.84.86.43', 'Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:24.0) Gecko/20100101 Firefox/24.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjNzMWQ5aTJieVRyekdoQktqMHNKOTVSM0ZtNkxjVHBQUnpYQk5PcloiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1747988282),
('S4arE9we2Hcir0uyl5Tb4wvjwS38jaJaeMsiSkLD', NULL, '3.84.86.43', 'Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:24.0) Gecko/20100101 Firefox/24.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlQ5UTBwWk4zUzZIWFJxQ3lkaHZpaDVFblJHUlNTUDFDbFRMaE5ZOEUiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1747993998),
('t59lUqrh9FUMundVUiIYDVSUgngPbIhRnl98shi8', NULL, '172.245.241.123', 'Mozilla/5.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IkdSQTFmZkpxcGhDak9vTVVTTnBuZ3B4WVRDUThIbXZpQ3BMaUhVdk4iO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748001473),
('jdGFqFTgMttL3pz3UkTTzieytmPO7n7s3z6Kg3xg', NULL, '159.203.10.139', 'Mozilla/5.0 (X11; Linux x86_64; rv:136.0) Gecko/20100101 Firefox/136.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InNEd1YyZ0taazh1TVQ1OXFVSjY1WFJ5TUFTTUJ2MlZDOU9hY0xsTVgiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748005365),
('kPh4HHepLIGbiQ0l9wJr34T9GawxBqsBxmtY2m2E', NULL, '159.203.10.139', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IkVhSFM2S0x5NjFRdVZKUGlja29xQnE5WVFXSXlwNXo5VEY0T0RMZlciO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748005366),
('jJICjKsF7QjqfgbwKIOgyLQW4PqbXE1lqrHZW9dv', NULL, '86.252.6.105', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlZoWXNCZXdaZFdhbjJlT05DNUZrZzZWV0YzOUwyclpROHJ2eGZ4ZVoiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748066738),
('UVohDAgnlwKCKpLDmZ54ZZLgmQFXVdpGmmEENnYz', NULL, '178.128.235.169', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjBablduOXN4S0VDMTlWNDJGa3RNVUNuNGpRNjFTaW9FWGlrWnhsVVMiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748070361),
('6mm1FuEEeG5Yk7bn7U9qbHgJT5kyUdyBawYfgNKr', NULL, '178.128.235.169', 'Mozilla/5.0 (X11; Linux x86_64; rv:136.0) Gecko/20100101 Firefox/136.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlVwMzhwVlRiYUE3U0lveHFDQ3l0QU80QkJzZDF4Q3ExNnJNTWNNNTUiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748070362),
('H40IScjx366DGv9hMlBI6L78OLPeTWH73EnNshLs', NULL, '86.252.6.105', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Ik5QYTZXR0EzSEUwWlhyUVc2dWdxQWFtcXNSZ1F3Qm10Y21QQ0hPNXAiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748074926),
('oCEKrp4Zo8tb3wl6rAnDrsCksgkjoTJqnOerfbfk', NULL, '86.252.6.105', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlVVTXBhd3QzYlBuWmJnT2VZRzAxUlN0MDk2VHF3dlFNaGo2U3BpU2wiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748075067),
('nrjaoqcfH5pbCXmwvHWxUgd5xATX6nZPp1HZ3qzR', NULL, '206.189.1.237', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InJUZWJPdEVBUE1hTjc4TE1tQnNHVXZxZndTMkF1eFA1TjlMTFRGT3YiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748161100),
('wN90Hj71OK3vcuTtWzr8nI56Hql3wKxyLHfvN3se', NULL, '206.189.1.237', 'Mozilla/5.0 (X11; Linux x86_64; rv:136.0) Gecko/20100101 Firefox/136.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IkkxUXNVQlQzQVVlcngxMENYZDhFSFJ0Q3JjZ0FQSFdhWUhuTFRkWHUiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1748161100),
('AxAl3IkD2qGh3xLbCScjvUl3IL4EugZxjBN2j9HO', NULL, '144.126.227.170', 'Mozilla/5.0 (X11; Linux x86_64; rv:136.0) Gecko/20100101 Firefox/136.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlRJZE1XTk9ITk5pWmttdU9CMEVGZ3dINzRMSFlGeWsxd080VHprbGEiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748175882),
('3JQvEzxpBZimj1ETVs2GggOBqTLxtSUrsYIFyVy5', NULL, '144.126.227.170', 'Mozilla/5.0 (X11; Linux x86_64; rv:136.0) Gecko/20100101 Firefox/136.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjlxWGZqeFFhRUx0d2lzYnh1SmNBT0dYM0Q3U3FhUTN4bmJkd1NqdUEiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748175882),
('NJOiTk2WoY5xgUpvrU86TTvcbvPjiTIAC4eZyLOY', NULL, '185.247.137.7', 'Mozilla/5.0 (compatible; InternetMeasurement/1.0; +https://internet-measurement.com/)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InBXcnJIRkpNRlVlMTBsc2ZYU2FUalpoNTN5alZmRHpLTmxKUTJQNTciO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748201061),
('TnrRHkwPWgi4kngilb58CLvrvZL8RqAAg3W0LDuk', NULL, '185.247.137.64', 'Mozilla/5.0 (compatible; InternetMeasurement/1.0; +https://internet-measurement.com/)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlhMcVBPZ2xUeWtoUm04SGtKU2NudnZtNkNHUWdpbG04d0JXYmVRa1IiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748209174),
('hDTwUKgQDEDkXUfv9FQGwH89o9pRKVnT9tNrken0', NULL, '24.199.124.203', 'vercel-screenshot/1.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IncyZkZ0SWJ1OHM0aGs0RzI5S2oyWXM1RjhJNU51TElnNm12aW53Uk0iO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748276375),
('N5COGUZwlvUyYhZo4vXX5ePrDA83MFfd0BiUqljE', NULL, '164.92.88.11', 'vercel-screenshot/1.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Ilc5RUI4Nnk0UlpTT1lNbXpaeWc1MFBpMFFFZkFxd2loME1xRUlLbkIiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748276375),
('2QLJtVKHe3IWBTLdAq5dZJjnwBPoMKr7Mgo18xVO', NULL, '139.59.157.166', 'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_6; en-en) AppleWebKit/533.19.4 (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Inl6dEpkbThPRHhzdWNYUTVranF0OWpFVkhKcWFnREpMY3lld2swVTciO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748299101),
('0meUuIUFZdBC5Doot67yRuyWA2PSzFDJhv0rmJqO', NULL, '128.199.51.127', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InpKV3RTR1ZNVnIwTEZnSzh5M3dydG40a0RCaUhSdk9BcUxQeWtsZnMiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1748329063),
('Or3KK7AobOgfmGJj77bVtVLCcqoNvdFE5g987UTu', NULL, '128.199.51.127', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImlkZlp6ZnlFSHRVR0xjMHpETFlEWVQ2WjBWTTJHYTA4b3hhZ1JXRlYiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748329063),
('tehukaiSsTs3OeVLNKDfQYOAD9ooJ0svTwOI1EgB', NULL, '161.35.81.116', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImFQVWNOY3FhekthMEp4MktNOFRPQTJrMUFZUHN1bFRuU0lQZ1JYYXUiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748349583),
('5ra5ZCYrNXtk0DHQnB6GqkAQYJxaeKwGB4PNZ3Xl', NULL, '161.35.81.116', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImlTajNUeExSQUR3aEt1dUNXdFhGRVpEWVVWbjRnN25YVWpLQzJQQlIiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748349583),
('hdcbzhuj5PMQP59cmPghJJPPjnuLBgXqB8zDiGCr', NULL, '129.0.76.225', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImlEUGRTbmNsY3h3OHZTWThGbG5sUGQwQm12S3VMUnJOQml4WDU3MHIiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748359103),
('GVdVZbi4GazElVLbFr1UPLpA9Lau0S7VXCVRyWQn', NULL, '170.39.218.58', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/121.0.0.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjZFZnM2ZDZsMTFUTldkbUxtY0g4R0JQdEZYcmRPM2owTG1zSzFpVG0iO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1748425132),
('yalelKI0vpNfGpYgMmEXsfjoErOpPriqF4knovm4', NULL, '102.244.223.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjBFTlN5bGdiVVJsdWg0WFNSZ2xROFlVUXlVZk5ocFNoUkY4UTZySlYiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748442015),
('qA01FtSw9vIFysrSck3g19IBaBUHEGbImfUTWcbb', NULL, '162.243.173.178', 'Mozilla/5.0 (X11; Linux x86_64; rv:136.0) Gecko/20100101 Firefox/136.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImRXRVMxTVZTNnVKaTZqZUcwTXhVSDI2NlRFOE1TTld4WElkSkVGT20iO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1748502154),
('p4QWfZPLSTx829mgBWdQXMvUD0DmycNVt4Yu9XXa', NULL, '162.243.173.178', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImFzcm9nbnJvZ2dnNGRjYmQ1WWZPblZEYUUwVEhxNFAwYkJOb3dmc0wiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748502154),
('LYr6mL4qt4k4nA2vkFrcMaCKBEltZYCl7Td0zj6W', NULL, '91.137.27.140', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IkVBbVRaekEzQ0xEaUU5blIwbHFyRFp3NG5YV0pGYXBwT2U3QnBhVGgiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748505717),
('NmPt8jM9NICRAWmkWdbWcMZ5UTUEiqxsOWDRpGhe', NULL, '209.38.122.146', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Imhvc0h4TnJkRjlzTTdzTjRjNWN2OXlGU1JxNG55aWRxdUIzSDBKTEciO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1748511081),
('wXKsPjNGowVvHDKsBSBxDdyVUyNeL11QNsytJMWk', NULL, '209.38.122.146', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjFNVHM5aVRjd0hGSldvdGx4UHRnMXJlSEpCWGVSSW5PdlhXUjR1N0UiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748511081),
('75z7gxAXC7WcR0u3z0lfoeLJ0VlDwiQgjRD7qUaT', NULL, '157.245.11.11', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6Ilhad3VSSlFCeGlnaTIyYnNJZ3B3eEhxMm4zc0t6c3ZjSmF1TFVCYjAiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748528512),
('EK9nBDqVdrxVhRYZS0LagFigWNN8izrKVUKELeP0', NULL, '157.245.11.11', 'Mozilla/5.0 (X11; Linux x86_64; rv:136.0) Gecko/20100101 Firefox/136.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IkE4QmtJT0UzWmZVYWN6cmdEeGRRc04zZUFoeUg2UU5oc1M1OW94MWQiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748528512),
('8cX8epVDXOVWPk6EsZmdH5Nw2lFyUyMEWjOTEIFe', NULL, '137.184.42.122', 'vercel-screenshot/1.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjdGUmMxWmoxckRPZVNGTXgyb2M2RjB0TjZQOGpDemQzWUNUYUliUzIiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748532513),
('C9FDoH0WTPVsricAMm0ikbtgXu3yTvl3dyZMPaI6', NULL, '128.199.14.227', 'vercel-screenshot/1.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlR2YTNhYmRrdXpKOVFCeXV6YWVPSFRHUEQ3bEMxQmdjb0w1UXVEV2QiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748532514),
('Sf4o816F2SD8NN8WKgDXNF8tWkZ9kpbvyRYmpaoq', NULL, '3.208.116.168', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IkZ1OXBpYWU3bEFBQk0zM0NQM0kxVTYweUo4ZWNibUdwSWVFdEp3SnoiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748578667),
('nuT1Ct2ZmhEA5CQVgENI9Y5WvZqN7pMOr7XeDnfE', NULL, '3.208.116.168', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InJzQ0JtWGhxQm54Ym5LRWt1Nkg2UDM2bE5CVmdNY2c0VFhndnQ3MzkiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748580581),
('ZuEOINRKumf0r6Lb5EaJFtU8UB6EyhMPiqtl51ZH', NULL, '193.32.126.132', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IkRYMGNHdDFXYlgxWm5DMDZiSzFVbFhUTWpGUXZhRUJhNzZxbHVqeGciO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748613986),
('w6Ist6xsGYPRPblbXKl6cmzamiwWIskKBPwNE6z5', NULL, '144.126.211.153', 'vercel-screenshot/1.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImN3UUtRZ0ljUW0yWmZMNk00VnpzaXZrcGZKVGJCaHd4WVlpOGh6U2oiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748622246),
('PoXoTVHTcLVgSIkiYg6y193bTWf6quyNPk0vMRRf', NULL, '146.190.155.243', 'vercel-screenshot/1.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlJiUlVSejVibURXMjFYR0JzVWVKOU1UQWlYS0VRTDh0Slc5c0kwNEEiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748622246),
('Zr7SpcKN5ZhPsgMkSXydci2L8z1DMUbdwoXzVz73', NULL, '192.241.146.30', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6InhwcEdDQWF0VjdRNnlRSVgzZnhObjJ5MTBpWE1BaHkxckhmclZ2ekwiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1748678396),
('X3j1SsTxv3pRacAvaQH1lj65seC9DHG8tw01C6dx', NULL, '192.241.146.30', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImlOcENZS2ZGbXlPb3RyR2VzUWR6VWVSUjUxaDRxQzNzRWVTeXBUa0EiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748678397),
('LZ7821OJi56p2eTWbqajZtxr8k316xsX0kw33gMZ', NULL, '157.245.208.243', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImxxNXhaZFlKWGx6cEZXU0FQQVpNVXlLZE93aDdRQkR0NHlqdXYxUkIiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjQwOiJodHRwczovL3d3dy5jZXJ0aWZpY2F0aW9uLWFwaS5jZnBjc2wuY29tIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1748701336),
('RAOnydnzKbRHdoRIu0Rry4IDxpLpEa5RnAYGzRiX', NULL, '157.245.208.243', 'Mozilla/5.0 (X11; Linux x86_64; rv:136.0) Gecko/20100101 Firefox/136.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlBnUFAzOWFXZG45WWRGeFNUR1Y2czh0MkdscVFqbVN1QkVvb3NZNmwiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748701336),
('SdC5tRQwZAypKStzlc5RjtGycFwLN6uiA3KlfBVo', NULL, '164.92.90.102', 'vercel-screenshot/1.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImZQVExEb0x0eW5FUjVjRXdRTWRYTE0xOE94UndlVjVyTFpEUHhRQU0iO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748713385),
('DkAnfXYB2iX6nP8kfH6ksuwMq5udfpca9fktRnFW', NULL, '164.92.90.102', 'vercel-screenshot/1.0', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImVOUURPaHJnejA2NHFqWDVFc3ZaNkhxMVpIMDdZRFJ0c2E2RmRWa3IiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748713385),
('g3NeDmMy8EBahcgO1hcyiVJKWs8BrLrRP9AJAAyM', NULL, '161.97.76.58', 'Mozilla/5.0 (compatible; MJ12bot/v1.4.8; http://mj12bot.com/)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImdRWWp1Z21jTHhNZzRIOGk5UFVXaW05bTBJNmxVVDQ1WmMwR2ZGTWsiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1748755393),
('VckemFBfHz4VuH9IvfoCUwPCLNRFNU9o0yG9MKMI', NULL, '198.235.24.13', '', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjdwaDhUNGZCbVh3NkF6NVVyTnQzNXJTNGhvbHFkakozSTdsTHpyRnAiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1748778945),
('t3JkRjRPQzHk3B0ixukbybNfuGzLpyJMx5JECGsD', NULL, '63.34.145.198', 'Mozilla/5.0 (compatible; NetcraftSurveyAgent/1.0; +info@netcraft.com)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImNlSnlFSGFXQjh3NVRDSEw2TVp1YVl5anVRVEJNRDRocmtsZHJud2UiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM5OiJodHRwOi8vd3d3LmNlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748796617),
('gZMKhwAUY44gYaqOCbXL1U4pyCXQUYus5bQByttx', NULL, '185.247.137.167', 'Mozilla/5.0 (compatible; InternetMeasurement/1.0; +https://internet-measurement.com/)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IlJtTDVlaGpsZ1JJV1dHMFJEeEhtMmtOSVhCeXdVdEp3ZEhqdG5yMFciO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwczovL2NlcnRpZmljYXRpb24tYXBpLmNmcGNzbC5jb20iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1748798084),
('yfFu7cLF3rbzti1M9m7v2hkLeOutyOjGLRbtMRje', NULL, '185.247.137.95', 'Mozilla/5.0 (compatible; InternetMeasurement/1.0; +https://internet-measurement.com/)', 'YTo0OntzOjIyOiJjdXJyZW50X2Vudmlyb25tZW50X2lkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6ImtGdmpKSmNOVWw3TGZTUDZ0UEIySnBXeDF0aFRteDFSaExRVXJFMTAiO3M6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM1OiJodHRwOi8vY2VydGlmaWNhdGlvbi1hcGkuY2ZwY3NsLmNvbSI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1748863644);

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `environment_id` bigint(20) UNSIGNED NOT NULL,
  `plan_id` bigint(20) UNSIGNED NOT NULL,
  `billing_cycle` varchar(191) NOT NULL,
  `status` varchar(191) NOT NULL DEFAULT 'active',
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `canceled_at` timestamp NULL DEFAULT NULL,
  `payment_method` varchar(191) DEFAULT NULL,
  `payment_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payment_details`)),
  `last_payment_at` timestamp NULL DEFAULT NULL,
  `next_payment_at` timestamp NULL DEFAULT NULL,
  `setup_fee_paid` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `personal_team` tinyint(1) NOT NULL,
  `environment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_invitations`
--

CREATE TABLE `team_invitations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `team_id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(191) NOT NULL,
  `role` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_members`
--

CREATE TABLE `team_members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `team_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `environment_id` bigint(20) UNSIGNED NOT NULL,
  `environment_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `role` varchar(191) NOT NULL DEFAULT 'member',
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `joined_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_user`
--

CREATE TABLE `team_user` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `team_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `templates`
--

CREATE TABLE `templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(191) NOT NULL DEFAULT 'draft',
  `thumbnail_path` text DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `team_id` bigint(20) UNSIGNED DEFAULT NULL,
  `environment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `templates`
--

INSERT INTO `templates` (`id`, `title`, `description`, `status`, `thumbnail_path`, `is_public`, `settings`, `created_by`, `team_id`, `environment_id`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Travail en hauteur', NULL, 'draft', NULL, 1, NULL, 2, NULL, 1, '2025-04-15 06:38:26', '2025-04-15 06:38:26', NULL),
(2, 'Techniques d\'élingage courant', NULL, 'draft', NULL, 1, NULL, 2, NULL, 1, '2025-04-15 10:23:59', '2025-04-15 10:23:59', NULL),
(3, 'Interventions en espace confiné', NULL, 'draft', NULL, 1, NULL, 2, NULL, 1, '2025-04-15 10:24:06', '2025-04-15 10:24:06', NULL),
(4, 'Enquêtes post-accidentelles efficaces', NULL, 'draft', NULL, 1, NULL, 2, NULL, 1, '2025-04-15 10:24:15', '2025-04-15 10:24:15', NULL),
(5, 'Analyse des dangers et mesures de contrôle', NULL, 'draft', NULL, 1, NULL, 2, NULL, 1, '2025-04-15 10:24:24', '2025-04-15 10:24:24', NULL),
(6, 'Test Travail en hauteur', 'Test Travail en hauteur desc', 'published', NULL, 1, NULL, 2, NULL, 1, '2025-05-30 16:24:44', '2025-05-30 17:01:32', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `text_contents`
--

CREATE TABLE `text_contents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `activity_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `content` longtext NOT NULL,
  `format` enum('plain','markdown','html') NOT NULL DEFAULT 'markdown',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `third_party_services`
--

CREATE TABLE `third_party_services` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `base_url` varchar(191) NOT NULL,
  `api_key` varchar(191) DEFAULT NULL,
  `api_secret` varchar(191) DEFAULT NULL,
  `bearer_token` text DEFAULT NULL,
  `username` varchar(191) DEFAULT NULL,
  `password` varchar(191) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `service_type` varchar(191) NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `third_party_services`
--

INSERT INTO `third_party_services` (`id`, `name`, `description`, `base_url`, `api_key`, `api_secret`, `bearer_token`, `username`, `password`, `is_active`, `service_type`, `config`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Certificate Generation Service', 'Service for generating and managing certificates', 'https://54.227.222.67', '', NULL, '3|bV0dd4uLRwwXocPR7zjmyXJwASSmtyJS6uimTJF30dbe09c6', 'admin@cslcertificates.com', 'kwbiCamn@1990', 1, 'certificate_generation', '\"{\\\"verify_ssl\\\":false,\\\"timeout\\\":60}\"', '2025-05-27 15:10:41', '2025-05-30 16:54:14', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `transaction_id` varchar(100) NOT NULL,
  `environment_id` bigint(20) UNSIGNED NOT NULL,
  `payment_gateway_setting_id` bigint(20) UNSIGNED DEFAULT NULL,
  `order_id` varchar(191) DEFAULT NULL,
  `invoice_id` varchar(191) DEFAULT NULL,
  `customer_id` varchar(191) DEFAULT NULL,
  `customer_email` varchar(191) DEFAULT NULL,
  `customer_name` varchar(191) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `fee_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `currency` varchar(191) NOT NULL DEFAULT 'USD',
  `status` varchar(191) NOT NULL DEFAULT 'pending',
  `payment_method` varchar(191) DEFAULT NULL,
  `payment_method_details` varchar(191) DEFAULT NULL,
  `gateway_transaction_id` varchar(191) DEFAULT NULL,
  `gateway_status` varchar(191) DEFAULT NULL,
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `description` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `ip_address` varchar(191) DEFAULT NULL,
  `user_agent` varchar(191) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `refunded_at` timestamp NULL DEFAULT NULL,
  `refund_reason` varchar(191) DEFAULT NULL,
  `created_by` varchar(191) DEFAULT NULL,
  `updated_by` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `transaction_id`, `environment_id`, `payment_gateway_setting_id`, `order_id`, `invoice_id`, `customer_id`, `customer_email`, `customer_name`, `amount`, `fee_amount`, `tax_amount`, `total_amount`, `currency`, `status`, `payment_method`, `payment_method_details`, `gateway_transaction_id`, `gateway_status`, `gateway_response`, `description`, `notes`, `ip_address`, `user_agent`, `paid_at`, `refunded_at`, `refund_reason`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'TXN_cf054b93-7a08-4138-ac05-cf67eac99924', 1, NULL, '1', NULL, '7', 'sales@cfpcsl.com', 'Rachel simo', 50.00, 0.00, 0.00, 50.00, 'USD', 'pending', 'stripe', NULL, NULL, NULL, NULL, 'Payment for Order #ORD-NSUFOEB3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-06-02 11:56:40', '2025-06-02 11:56:40', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `company_name` varchar(191) DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `role` varchar(191) NOT NULL DEFAULT 'learner',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(191) NOT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `current_team_id` bigint(20) UNSIGNED DEFAULT NULL,
  `profile_photo_path` varchar(2048) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `company_name`, `email`, `role`, `email_verified_at`, `password`, `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `remember_token`, `current_team_id`, `profile_photo_path`, `created_at`, `updated_at`) VALUES
(1, 'Admin User', NULL, 'admin@csl-certification.com', 'super_admin', '2025-04-12 20:51:27', '$2y$12$BxMiSURH6KLUqdAFgJsyR.bgQ2MbYqvJJhsfFZ8Nng7bxQ3ttFAMy', NULL, NULL, NULL, NULL, NULL, NULL, '2025-04-12 20:51:27', '2025-04-12 20:51:27'),
(2, 'CSL', NULL, 'direction@cfpcsl.com', 'company_teacher', NULL, '$2y$12$Yrt.V4XZUJ1sQbsYad8uBup15FAeTTEKkWz2nUDR6MD7DyQTotF8W', NULL, NULL, NULL, NULL, NULL, 'https://res.cloudinary.com/dhzghklvu/image/upload/v1745596601/x8rmtlfxbgczb5aknukx.png', '2025-04-12 20:51:28', '2025-04-25 15:56:42'),
(3, 'Individual Teacher', NULL, 'individual.teacher@example.com', 'individual_teacher', NULL, '$2y$12$KSyprKOjX4NefnXK5mpRg.PafL5oz06ORAr3BbaEXiinsht1yLgGy', NULL, NULL, NULL, NULL, NULL, NULL, '2025-04-12 20:51:28', '2025-04-12 20:51:28'),
(4, 'Learner One', NULL, 'learner.one@example.com', 'learner', NULL, '$2y$12$ywv2NJnGxgzSNe8kzF8gbueoe6ztAyOhf9XfkbIgtgqIPKbahmRKW', NULL, NULL, NULL, NULL, NULL, NULL, '2025-04-12 20:51:28', '2025-04-12 20:51:28'),
(5, 'Learner Two', NULL, 'learner.two@example.com', 'learner', NULL, '$2y$12$0XFcRh.5C4tgxun9dDcW0eKi9wlJhpvDKLLkFeeoz5M1886FXMC/m', NULL, NULL, NULL, NULL, NULL, NULL, '2025-04-12 20:51:28', '2025-04-12 20:51:28'),
(6, 'Test User', NULL, 'test@example.com', 'learner', '2025-04-12 20:51:29', '$2y$12$HnbxZSyAH1xrsN8mOlBrpOeeIppiDyZzxYfpXuuNnpdpE9c0nYjIy', NULL, NULL, NULL, '5j4g55NBS8', NULL, NULL, '2025-04-12 20:51:30', '2025-04-12 20:51:30'),
(7, 'Rachel simo', NULL, 'sales@cfpcsl.com', 'learner', NULL, '$2y$12$/E9eyLBSZCS.xu887rFFuum4oVdYJKhRjEVL8aq7.zNHfzKi1YRHy', NULL, NULL, NULL, NULL, NULL, NULL, '2025-06-02 11:56:37', '2025-06-02 11:56:37');

-- --------------------------------------------------------

--
-- Table structure for table `video_contents`
--

CREATE TABLE `video_contents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `activity_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `video_url` varchar(191) NOT NULL,
  `video_type` varchar(191) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `thumbnail_url` varchar(191) DEFAULT NULL,
  `transcript` longtext DEFAULT NULL,
  `captions_url` varchar(191) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activities`
--
ALTER TABLE `activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activities_created_by_foreign` (`created_by`),
  ADD KEY `activities_block_id_order_index` (`block_id`,`order`),
  ADD KEY `activities_status_index` (`status`),
  ADD KEY `activities_type_index` (`type`),
  ADD KEY `activities_content_type_content_id_index` (`content_type`,`content_id`);

--
-- Indexes for table `activity_completions`
--
ALTER TABLE `activity_completions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_activity_completion` (`enrollment_id`,`activity_id`),
  ADD KEY `activity_completions_activity_id_foreign` (`activity_id`),
  ADD KEY `activity_completions_enrollment_id_activity_id_index` (`enrollment_id`,`activity_id`),
  ADD KEY `activity_completions_status_index` (`status`),
  ADD KEY `activity_completions_completed_at_index` (`completed_at`);

--
-- Indexes for table `assignment_contents`
--
ALTER TABLE `assignment_contents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assignment_contents_created_by_foreign` (`created_by`),
  ADD KEY `assignment_contents_due_date_index` (`due_date`),
  ADD KEY `assignment_contents_activity_id_index` (`activity_id`);

--
-- Indexes for table `assignment_criteria`
--
ALTER TABLE `assignment_criteria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assignment_criteria_assignment_content_id_order_index` (`assignment_content_id`,`order`);

--
-- Indexes for table `assignment_criterion_scores`
--
ALTER TABLE `assignment_criterion_scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_criterion_score` (`assignment_submission_id`,`assignment_criterion_id`),
  ADD KEY `assignment_criterion_scores_assignment_submission_id_index` (`assignment_submission_id`),
  ADD KEY `assignment_criterion_scores_assignment_criterion_id_index` (`assignment_criterion_id`);

--
-- Indexes for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assignment_submissions_user_id_foreign` (`user_id`),
  ADD KEY `assignment_submissions_assignment_content_id_user_id_index` (`assignment_content_id`,`user_id`),
  ADD KEY `assignment_submissions_status_index` (`status`),
  ADD KEY `assignment_submissions_submitted_at_index` (`submitted_at`),
  ADD KEY `assignment_submissions_graded_by_index` (`graded_by`);

--
-- Indexes for table `assignment_submission_files`
--
ALTER TABLE `assignment_submission_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assignment_submission_files_assignment_submission_id_index` (`assignment_submission_id`),
  ADD KEY `assignment_submission_files_file_type_index` (`file_type`),
  ADD KEY `assignment_submission_files_is_video_index` (`is_video`);

--
-- Indexes for table `blocks`
--
ALTER TABLE `blocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `blocks_created_by_foreign` (`created_by`),
  ADD KEY `blocks_template_id_order_index` (`template_id`,`order`),
  ADD KEY `blocks_status_index` (`status`);

--
-- Indexes for table `brandings`
--
ALTER TABLE `brandings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `brandings_custom_domain_unique` (`custom_domain`),
  ADD KEY `brandings_user_id_index` (`user_id`),
  ADD KEY `brandings_is_active_index` (`is_active`),
  ADD KEY `brandings_custom_domain_index` (`custom_domain`),
  ADD KEY `brandings_environment_id_index` (`environment_id`);

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
-- Indexes for table `certificate_contents`
--
ALTER TABLE `certificate_contents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `certificate_contents_created_by_foreign` (`created_by`),
  ADD KEY `certificate_contents_auto_issue_index` (`auto_issue`),
  ADD KEY `certificate_contents_activity_id_foreign` (`activity_id`);

--
-- Indexes for table `certificate_templates`
--
ALTER TABLE `certificate_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `courses_template_id_index` (`template_id`),
  ADD KEY `courses_created_by_index` (`created_by`),
  ADD KEY `courses_status_index` (`status`),
  ADD KEY `courses_start_date_end_date_index` (`start_date`,`end_date`),
  ADD KEY `courses_difficulty_level_index` (`difficulty_level`),
  ADD KEY `courses_environment_id_index` (`environment_id`),
  ADD KEY `courses_slug_index` (`slug`);

--
-- Indexes for table `course_sections`
--
ALTER TABLE `course_sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_sections_created_by_foreign` (`created_by`),
  ADD KEY `course_sections_course_id_index` (`course_id`),
  ADD KEY `course_sections_order_index` (`order`),
  ADD KEY `course_sections_is_published_index` (`is_published`);

--
-- Indexes for table `course_section_items`
--
ALTER TABLE `course_section_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_section_items_created_by_foreign` (`created_by`),
  ADD KEY `course_section_items_course_section_id_index` (`course_section_id`),
  ADD KEY `course_section_items_activity_id_index` (`activity_id`),
  ADD KEY `course_section_items_order_index` (`order`),
  ADD KEY `course_section_items_is_published_index` (`is_published`),
  ADD KEY `course_section_items_is_required_index` (`is_required`);

--
-- Indexes for table `documentation_attachments`
--
ALTER TABLE `documentation_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `documentation_attachments_created_by_foreign` (`created_by`),
  ADD KEY `documentation_attachments_documentation_content_id_index` (`documentation_content_id`),
  ADD KEY `documentation_attachments_file_type_index` (`file_type`);

--
-- Indexes for table `documentation_contents`
--
ALTER TABLE `documentation_contents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `documentation_contents_created_by_foreign` (`created_by`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`course_id`,`user_id`),
  ADD KEY `enrollments_user_id_foreign` (`user_id`),
  ADD KEY `enrollments_enrolled_by_foreign` (`enrolled_by`),
  ADD KEY `enrollments_course_id_user_id_index` (`course_id`,`user_id`),
  ADD KEY `enrollments_status_index` (`status`),
  ADD KEY `enrollments_enrolled_at_index` (`enrolled_at`),
  ADD KEY `enrollments_completed_at_index` (`completed_at`),
  ADD KEY `enrollments_environment_id_foreign` (`environment_id`);

--
-- Indexes for table `environments`
--
ALTER TABLE `environments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `environments_primary_domain_unique` (`primary_domain`),
  ADD KEY `environments_owner_id_foreign` (`owner_id`);

--
-- Indexes for table `environment_referrals`
--
ALTER TABLE `environment_referrals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `environment_referrals_code_unique` (`code`);

--
-- Indexes for table `environment_user`
--
ALTER TABLE `environment_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `environment_user_environment_id_user_id_unique` (`environment_id`,`user_id`),
  ADD KEY `environment_user_user_id_foreign` (`user_id`);

--
-- Indexes for table `event_contents`
--
ALTER TABLE `event_contents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_contents_created_by_foreign` (`created_by`),
  ADD KEY `event_contents_event_type_index` (`event_type`),
  ADD KEY `event_contents_start_date_index` (`start_date`),
  ADD KEY `event_contents_end_date_index` (`end_date`),
  ADD KEY `event_contents_is_webinar_index` (`is_webinar`);

--
-- Indexes for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_event_registration` (`event_content_id`,`user_id`),
  ADD KEY `event_registrations_user_id_foreign` (`user_id`),
  ADD KEY `event_registrations_attendance_confirmed_by_foreign` (`attendance_confirmed_by`),
  ADD KEY `event_registrations_event_content_id_user_id_index` (`event_content_id`,`user_id`),
  ADD KEY `event_registrations_status_index` (`status`),
  ADD KEY `event_registrations_registration_date_index` (`registration_date`);

--
-- Indexes for table `event_sessions`
--
ALTER TABLE `event_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_sessions_created_by_foreign` (`created_by`),
  ADD KEY `event_sessions_event_content_id_index` (`event_content_id`),
  ADD KEY `event_sessions_start_time_end_time_index` (`start_time`,`end_time`),
  ADD KEY `event_sessions_is_mandatory_index` (`is_mandatory`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `feedback_answers`
--
ALTER TABLE `feedback_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_feedback_answer` (`feedback_submission_id`,`feedback_question_id`),
  ADD KEY `feedback_answers_feedback_submission_id_index` (`feedback_submission_id`),
  ADD KEY `feedback_answers_feedback_question_id_index` (`feedback_question_id`);

--
-- Indexes for table `feedback_contents`
--
ALTER TABLE `feedback_contents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `feedback_contents_created_by_foreign` (`created_by`),
  ADD KEY `feedback_contents_feedback_type_index` (`feedback_type`),
  ADD KEY `feedback_contents_is_anonymous_index` (`allow_anonymous`),
  ADD KEY `feedback_contents_start_date_end_date_index` (`start_date`,`end_date`),
  ADD KEY `feedback_contents_activity_id_index` (`activity_id`);

--
-- Indexes for table `feedback_questions`
--
ALTER TABLE `feedback_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `feedback_questions_created_by_foreign` (`created_by`),
  ADD KEY `feedback_questions_feedback_content_id_index` (`feedback_content_id`),
  ADD KEY `feedback_questions_order_index` (`order`);

--
-- Indexes for table `feedback_submissions`
--
ALTER TABLE `feedback_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `feedback_submissions_user_id_foreign` (`user_id`),
  ADD KEY `feedback_submissions_reviewed_by_foreign` (`reviewed_by`),
  ADD KEY `feedback_submissions_feedback_content_id_user_id_index` (`feedback_content_id`,`user_id`),
  ADD KEY `feedback_submissions_status_index` (`status`),
  ADD KEY `feedback_submissions_submission_date_index` (`submission_date`);

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `files_environment_id_foreign` (`environment_id`);

--
-- Indexes for table `issued_certificates`
--
ALTER TABLE `issued_certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `issued_certificates_certificate_number_unique` (`certificate_number`),
  ADD KEY `issued_certificates_user_id_foreign` (`user_id`),
  ADD KEY `issued_certificates_revoked_by_foreign` (`revoked_by`),
  ADD KEY `issued_certificates_certificate_content_id_user_id_index` (`certificate_content_id`,`user_id`),
  ADD KEY `issued_certificates_certificate_number_index` (`certificate_number`),
  ADD KEY `issued_certificates_status_index` (`status`),
  ADD KEY `issued_certificates_issued_date_index` (`issued_date`),
  ADD KEY `issued_certificates_expiry_date_index` (`expiry_date`),
  ADD KEY `issued_certificates_course_id_index` (`course_id`),
  ADD KEY `issued_certificates_environment_id_index` (`environment_id`);

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
-- Indexes for table `lesson_contents`
--
ALTER TABLE `lesson_contents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lesson_contents_created_by_foreign` (`created_by`),
  ADD KEY `lesson_contents_activity_id_foreign` (`activity_id`);

--
-- Indexes for table `lesson_content_parts`
--
ALTER TABLE `lesson_content_parts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lesson_content_parts_created_by_foreign` (`created_by`),
  ADD KEY `lesson_content_parts_lesson_content_id_order_index` (`lesson_content_id`,`order`),
  ADD KEY `lesson_content_parts_content_type_index` (`content_type`);

--
-- Indexes for table `lesson_discussions`
--
ALTER TABLE `lesson_discussions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lesson_discussions_lesson_content_id_index` (`lesson_content_id`),
  ADD KEY `lesson_discussions_content_part_id_index` (`content_part_id`),
  ADD KEY `lesson_discussions_question_id_index` (`question_id`),
  ADD KEY `lesson_discussions_user_id_index` (`user_id`),
  ADD KEY `lesson_discussions_parent_id_index` (`parent_id`),
  ADD KEY `lesson_discussions_is_instructor_feedback_index` (`is_instructor_feedback`);

--
-- Indexes for table `lesson_questions`
--
ALTER TABLE `lesson_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lesson_questions_created_by_foreign` (`created_by`),
  ADD KEY `lesson_questions_lesson_content_id_order_index` (`lesson_content_id`,`order`),
  ADD KEY `lesson_questions_content_part_id_index` (`content_part_id`),
  ADD KEY `lesson_questions_question_type_index` (`question_type`),
  ADD KEY `lesson_questions_is_scorable_index` (`is_scorable`);

--
-- Indexes for table `lesson_question_options`
--
ALTER TABLE `lesson_question_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lesson_question_options_lesson_question_id_order_index` (`lesson_question_id`,`order`);

--
-- Indexes for table `lesson_question_responses`
--
ALTER TABLE `lesson_question_responses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_lesson_response` (`user_id`,`lesson_question_id`,`attempt_number`),
  ADD KEY `lesson_question_responses_lesson_question_id_foreign` (`lesson_question_id`),
  ADD KEY `lesson_question_responses_lesson_content_id_foreign` (`lesson_content_id`),
  ADD KEY `lesson_question_responses_selected_option_id_foreign` (`selected_option_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `orders_order_number_unique` (`order_number`),
  ADD KEY `orders_user_id_index` (`user_id`),
  ADD KEY `orders_order_number_index` (`order_number`),
  ADD KEY `orders_status_index` (`status`),
  ADD KEY `orders_payment_method_index` (`payment_method`),
  ADD KEY `orders_referral_id_index` (`referral_id`),
  ADD KEY `orders_environment_id_index` (`environment_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_items_order_id_index` (`order_id`),
  ADD KEY `order_items_product_id_index` (`product_id`),
  ADD KEY `order_items_is_subscription_index` (`is_subscription`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `payment_gateway_settings`
--
ALTER TABLE `payment_gateway_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_gateway_settings_code_unique` (`code`),
  ADD KEY `idx_env_default` (`environment_id`,`is_default`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indexes for table `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `products_slug_environment_id_unique` (`slug`,`environment_id`),
  ADD KEY `products_created_by_index` (`created_by`),
  ADD KEY `products_status_index` (`status`),
  ADD KEY `products_is_subscription_index` (`is_subscription`),
  ADD KEY `products_environment_id_index` (`environment_id`),
  ADD KEY `products_category_id_foreign` (`category_id`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_categories_slug_environment_id_unique` (`slug`,`environment_id`),
  ADD KEY `product_categories_parent_id_foreign` (`parent_id`),
  ADD KEY `product_categories_environment_id_foreign` (`environment_id`),
  ADD KEY `product_categories_created_by_foreign` (`created_by`);

--
-- Indexes for table `product_courses`
--
ALTER TABLE `product_courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_course` (`product_id`,`course_id`),
  ADD KEY `product_courses_course_id_foreign` (`course_id`),
  ADD KEY `product_courses_product_id_course_id_index` (`product_id`,`course_id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_reviews_user_id_foreign` (`user_id`),
  ADD KEY `product_reviews_product_id_status_index` (`product_id`,`status`),
  ADD KEY `product_reviews_environment_id_product_id_index` (`environment_id`,`product_id`),
  ADD KEY `product_reviews_rating_index` (`rating`);

--
-- Indexes for table `quiz_contents`
--
ALTER TABLE `quiz_contents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quiz_contents_created_by_foreign` (`created_by`),
  ADD KEY `quiz_contents_activity_id_foreign` (`activity_id`);

--
-- Indexes for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quiz_questions_created_by_foreign` (`created_by`),
  ADD KEY `quiz_questions_quiz_content_id_order_index` (`quiz_content_id`,`order`),
  ADD KEY `quiz_questions_question_type_index` (`question_type`);

--
-- Indexes for table `quiz_question_options`
--
ALTER TABLE `quiz_question_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quiz_question_options_quiz_question_id_order_index` (`quiz_question_id`,`order`);

--
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `referrals_code_unique` (`code`),
  ADD KEY `referrals_referrer_id_index` (`referrer_id`),
  ADD KEY `referrals_code_index` (`code`),
  ADD KEY `referrals_is_active_index` (`is_active`),
  ADD KEY `referrals_expires_at_index` (`expires_at`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subscriptions_environment_id_foreign` (`environment_id`),
  ADD KEY `subscriptions_plan_id_foreign` (`plan_id`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teams_user_id_index` (`user_id`),
  ADD KEY `teams_environment_id_index` (`environment_id`);

--
-- Indexes for table `team_invitations`
--
ALTER TABLE `team_invitations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `team_invitations_team_id_email_unique` (`team_id`,`email`);

--
-- Indexes for table `team_members`
--
ALTER TABLE `team_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `team_members_team_id_user_id_unique` (`team_id`,`user_id`);

--
-- Indexes for table `team_user`
--
ALTER TABLE `team_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `team_user_team_id_user_id_unique` (`team_id`,`user_id`);

--
-- Indexes for table `templates`
--
ALTER TABLE `templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `templates_status_index` (`status`),
  ADD KEY `templates_created_by_index` (`created_by`),
  ADD KEY `templates_team_id_index` (`team_id`),
  ADD KEY `templates_environment_id_index` (`environment_id`);

--
-- Indexes for table `text_contents`
--
ALTER TABLE `text_contents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `text_contents_created_by_foreign` (`created_by`),
  ADD KEY `text_contents_activity_id_foreign` (`activity_id`);

--
-- Indexes for table `third_party_services`
--
ALTER TABLE `third_party_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `third_party_services_service_type_index` (`service_type`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transactions_transaction_id_unique` (`transaction_id`),
  ADD KEY `transactions_environment_id_foreign` (`environment_id`),
  ADD KEY `transactions_payment_gateway_setting_id_foreign` (`payment_gateway_setting_id`),
  ADD KEY `transactions_transaction_id_index` (`transaction_id`),
  ADD KEY `transactions_order_id_index` (`order_id`),
  ADD KEY `transactions_customer_id_index` (`customer_id`),
  ADD KEY `transactions_status_index` (`status`),
  ADD KEY `transactions_created_at_index` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- Indexes for table `video_contents`
--
ALTER TABLE `video_contents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `video_contents_created_by_foreign` (`created_by`),
  ADD KEY `video_contents_provider_index` (`video_type`),
  ADD KEY `video_contents_activity_id_foreign` (`activity_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activities`
--
ALTER TABLE `activities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `activity_completions`
--
ALTER TABLE `activity_completions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_contents`
--
ALTER TABLE `assignment_contents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_criteria`
--
ALTER TABLE `assignment_criteria`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_criterion_scores`
--
ALTER TABLE `assignment_criterion_scores`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_submission_files`
--
ALTER TABLE `assignment_submission_files`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blocks`
--
ALTER TABLE `blocks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `brandings`
--
ALTER TABLE `brandings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `certificate_contents`
--
ALTER TABLE `certificate_contents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `certificate_templates`
--
ALTER TABLE `certificate_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `course_sections`
--
ALTER TABLE `course_sections`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `course_section_items`
--
ALTER TABLE `course_section_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `documentation_attachments`
--
ALTER TABLE `documentation_attachments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documentation_contents`
--
ALTER TABLE `documentation_contents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `environments`
--
ALTER TABLE `environments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `environment_referrals`
--
ALTER TABLE `environment_referrals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `environment_user`
--
ALTER TABLE `environment_user`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `event_contents`
--
ALTER TABLE `event_contents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_registrations`
--
ALTER TABLE `event_registrations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_sessions`
--
ALTER TABLE `event_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback_answers`
--
ALTER TABLE `feedback_answers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback_contents`
--
ALTER TABLE `feedback_contents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback_questions`
--
ALTER TABLE `feedback_questions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback_submissions`
--
ALTER TABLE `feedback_submissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `issued_certificates`
--
ALTER TABLE `issued_certificates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lesson_contents`
--
ALTER TABLE `lesson_contents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lesson_content_parts`
--
ALTER TABLE `lesson_content_parts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lesson_discussions`
--
ALTER TABLE `lesson_discussions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lesson_questions`
--
ALTER TABLE `lesson_questions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lesson_question_options`
--
ALTER TABLE `lesson_question_options`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lesson_question_responses`
--
ALTER TABLE `lesson_question_responses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payment_gateway_settings`
--
ALTER TABLE `payment_gateway_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `plans`
--
ALTER TABLE `plans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `product_courses`
--
ALTER TABLE `product_courses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_contents`
--
ALTER TABLE `quiz_contents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `quiz_question_options`
--
ALTER TABLE `quiz_question_options`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_invitations`
--
ALTER TABLE `team_invitations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_members`
--
ALTER TABLE `team_members`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_user`
--
ALTER TABLE `team_user`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `templates`
--
ALTER TABLE `templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `text_contents`
--
ALTER TABLE `text_contents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `third_party_services`
--
ALTER TABLE `third_party_services`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `video_contents`
--
ALTER TABLE `video_contents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
