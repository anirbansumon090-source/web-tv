-- OTT KING Live TV MySQL / phpMyAdmin Database Schema
-- Database: `ottking_db`

CREATE DATABASE IF NOT EXISTS `ottking_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ottking_db`;

-- --------------------------------------------------------

-- Table structure for table `users`
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `package` varchar(100) DEFAULT 'Basic Plan',
  `expiry_date` date DEFAULT '2026-12-31',
  `bound_device_id` varchar(255) DEFAULT NULL,
  `session_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial demo users
INSERT INTO `users` (`id`, `username`, `password`, `package`, `expiry_date`, `bound_device_id`, `session_token`) VALUES
(1, 'admin', '123456', 'VIP Premium Ultra', '2030-12-31', NULL, NULL),
(2, 'user1', '1234', 'Basic Plan', '2026-12-31', NULL, NULL)
ON DUPLICATE KEY UPDATE `username`=`username`;

-- --------------------------------------------------------

-- Table structure for table `categories`
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT 'ic_tv',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial categories
INSERT INTO `categories` (`id`, `name`, `icon`) VALUES
(1, 'All Channels', 'ic_tv'),
(2, 'Sports Live', 'ic_play'),
(3, 'News & World', 'ic_info'),
(4, 'Movies & Cinema', 'ic_play'),
(5, 'Entertainment', 'ic_tv')
ON DUPLICATE KEY UPDATE `name`=`name`;

-- --------------------------------------------------------

-- Table structure for table `channels`
CREATE TABLE IF NOT EXISTS `channels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `logo_url` text DEFAULT NULL,
  `stream_url` text NOT NULL,
  `category_id` int(11) DEFAULT 1,
  `is_premium` tinyint(1) DEFAULT 0,
  `stream_type` varchar(20) DEFAULT 'hls',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial channels
INSERT INTO `channels` (`id`, `name`, `logo_url`, `stream_url`, `category_id`, `is_premium`, `stream_type`) VALUES
(1, 'OTT KING Sports 1 HD', 'https://picsum.photos/200/200?random=1', 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8', 2, 0, 'hls'),
(2, 'OTT KING Premium Sports 4K', 'https://picsum.photos/200/200?random=2', 'https://playertest.longtailvideo.com/adaptive/bbbell/bbbell.m3u8', 2, 1, 'hls'),
(3, 'World News 24/7', 'https://picsum.photos/200/200?random=3', 'https://devstreaming-cdn.apple.com/videos/streaming/examples/bipbop_4x3/bipbop_4x3_variant.m3u8', 3, 0, 'hls'),
(4, 'Action Movies Live', 'https://picsum.photos/200/200?random=4', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 4, 0, 'ts'),
(5, 'VIP Cinema Ultra', 'https://picsum.photos/200/200?random=5', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4', 4, 1, 'ts'),
(6, 'Entertainment Plus', 'https://picsum.photos/200/200?random=6', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4', 5, 0, 'ts')
ON DUPLICATE KEY UPDATE `name`=`name`;

-- --------------------------------------------------------

-- Table structure for table `notifications`
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `target_username` varchar(100) DEFAULT '',
  `target_package` varchar(100) DEFAULT '',
  `type` varchar(50) DEFAULT 'SYSTEM',
  `action_text` varchar(50) DEFAULT 'View',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial notifications
INSERT INTO `notifications` (`id`, `title`, `message`, `target_username`, `target_package`, `type`, `action_text`) VALUES
(1, 'Server Upgrade Notice', 'OTT KING Live TV server upgrade completed successfully. All 4K streams are active.', '', '', 'SYSTEM', 'OK'),
(2, 'VIP Account Special Welcome', 'Exclusive access active for VIP Users! Stream 4K Live Sports & Ultra Movies now.', 'admin', 'VIP Premium Ultra', 'USER', 'Explore VIP')
ON DUPLICATE KEY UPDATE `title`=`title`;

-- --------------------------------------------------------

-- Table structure for table `reports`
CREATE TABLE IF NOT EXISTS `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) DEFAULT 'Anonymous',
  `category` varchar(100) DEFAULT 'General Issue',
  `description` text DEFAULT NULL,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
