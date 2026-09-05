-- ---------------------------------------------------------------------------
-- GodsForum - complete database schema and seed data
-- MySQL 5.7+ / MariaDB 10.3+ , InnoDB, utf8mb4
--
-- Import with:
--   mysql -u root -p < database/godsforum.sql
-- ---------------------------------------------------------------------------

DROP DATABASE IF EXISTS `godsforum`;
CREATE DATABASE `godsforum` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `godsforum`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------------
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(32)  NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('member','moderator','admin') NOT NULL DEFAULT 'member',
  `status`        ENUM('active','banned') NOT NULL DEFAULT 'active',
  `signature`     VARCHAR(255) NOT NULL DEFAULT '',
  `avatar`        VARCHAR(120) NULL DEFAULT NULL,
  `theme`         VARCHAR(40)  NOT NULL DEFAULT 'parchment',
  `ban_reason`    VARCHAR(255) NOT NULL DEFAULT '',
  `banned_until`  DATETIME     NULL DEFAULT NULL,
  `banned_by`     INT UNSIGNED NULL DEFAULT NULL,
  `post_count`    INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` DATETIME     NULL DEFAULT NULL,
  `last_seen_at`  DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- categories (top level groupings shown on the board index)
-- ---------------------------------------------------------------------------
CREATE TABLE `categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(80)  NOT NULL,
  `slug`        VARCHAR(90)  NOT NULL,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `position`    INT          NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `idx_categories_position` (`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- boards (a discussion board inside a category)
-- ---------------------------------------------------------------------------
CREATE TABLE `boards` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `name`        VARCHAR(80)  NOT NULL,
  `slug`        VARCHAR(90)  NOT NULL,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `icon`        VARCHAR(40)  NOT NULL DEFAULT 'forum',
  `position`    INT          NOT NULL DEFAULT 0,
  `is_locked`   TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_boards_slug` (`slug`),
  KEY `idx_boards_category` (`category_id`),
  KEY `idx_boards_position` (`position`),
  CONSTRAINT `fk_boards_category` FOREIGN KEY (`category_id`)
    REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- topics
-- ---------------------------------------------------------------------------
CREATE TABLE `topics` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `board_id`     INT UNSIGNED NOT NULL,
  `user_id`      INT UNSIGNED NULL,
  `title`        VARCHAR(140) NOT NULL,
  `slug`         VARCHAR(150) NOT NULL,
  `is_pinned`    TINYINT(1)   NOT NULL DEFAULT 0,
  `is_locked`    TINYINT(1)   NOT NULL DEFAULT 0,
  `view_count`   INT UNSIGNED NOT NULL DEFAULT 0,
  `reply_count`  INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_post_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_topics_slug` (`slug`),
  KEY `idx_topics_board` (`board_id`),
  KEY `idx_topics_user` (`user_id`),
  KEY `idx_topics_last_post` (`last_post_at`),
  KEY `idx_topics_pinned` (`is_pinned`),
  FULLTEXT KEY `ft_topics_title` (`title`),
  CONSTRAINT `fk_topics_board` FOREIGN KEY (`board_id`)
    REFERENCES `boards` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_topics_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- posts (the first post of a topic is the opening message)
-- ---------------------------------------------------------------------------
CREATE TABLE `posts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ref`        CHAR(12)     NOT NULL,
  `topic_id`   INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NULL,
  `body`       TEXT         NOT NULL,
  `ip_address` VARCHAR(45)  NOT NULL DEFAULT '',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `edited_at`  DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_posts_ref` (`ref`),
  KEY `idx_posts_topic` (`topic_id`),
  KEY `idx_posts_user` (`user_id`),
  KEY `idx_posts_created` (`created_at`),
  FULLTEXT KEY `ft_posts_body` (`body`),
  CONSTRAINT `fk_posts_topic` FOREIGN KEY (`topic_id`)
    REFERENCES `topics` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- reports (members flagging posts for the staff)
-- ---------------------------------------------------------------------------
CREATE TABLE `reports` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id`     INT UNSIGNED NOT NULL,
  `reporter_id` INT UNSIGNED NULL,
  `reason`      VARCHAR(255) NOT NULL,
  `status`      ENUM('open','resolved') NOT NULL DEFAULT 'open',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reports_post` (`post_id`),
  KEY `idx_reports_status` (`status`),
  CONSTRAINT `fk_reports_post` FOREIGN KEY (`post_id`)
    REFERENCES `posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reports_user` FOREIGN KEY (`reporter_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- login_attempts (brute force throttling)
-- ---------------------------------------------------------------------------
CREATE TABLE `login_attempts` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier`   VARCHAR(190) NOT NULL,
  `ip_address`   VARCHAR(45)  NOT NULL,
  `success`      TINYINT(1)   NOT NULL DEFAULT 0,
  `attempted_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_identifier` (`identifier`),
  KEY `idx_attempts_ip` (`ip_address`),
  KEY `idx_attempts_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- admin_log (audit trail of every staff action)
-- ---------------------------------------------------------------------------
CREATE TABLE `admin_log` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`   INT UNSIGNED NULL,
  `action`     VARCHAR(100) NOT NULL,
  `details`    VARCHAR(500) NOT NULL DEFAULT '',
  `ip_address` VARCHAR(45)  NOT NULL DEFAULT '',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_adminlog_admin` (`admin_id`),
  KEY `idx_adminlog_time` (`created_at`),
  CONSTRAINT `fk_adminlog_user` FOREIGN KEY (`admin_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- bans (full history of every suspension, kept after a member is reinstated)
-- ---------------------------------------------------------------------------
CREATE TABLE `bans` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `staff_id`     INT UNSIGNED NULL,
  `reason`       VARCHAR(255) NOT NULL DEFAULT '',
  `expires_at`   DATETIME     NULL DEFAULT NULL,
  `lifted_at`    DATETIME     NULL DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bans_user` (`user_id`),
  KEY `idx_bans_expires` (`expires_at`),
  CONSTRAINT `fk_bans_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bans_staff` FOREIGN KEY (`staff_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- settings (editable from the admin control room)
-- ---------------------------------------------------------------------------
CREATE TABLE `settings` (
  `setting_key`   VARCHAR(60)  NOT NULL,
  `setting_value` VARCHAR(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===========================================================================
-- SEED DATA
-- ===========================================================================

-- Password for every seeded account below is:  Password123!
-- (bcrypt hash generated with password_hash(..., PASSWORD_DEFAULT))
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `theme`, `signature`, `post_count`, `created_at`, `last_seen_at`) VALUES
(1, 'admin',      'admin@godsforum.test',     '$2y$12$0rptww8tRYk4QMYlyhkmnuEqAvXqmp4Im0UTha42mXENZs3ok/gyS', 'admin',     'parchment', 'Board administrator.',                       0, '2024-01-01 08:00:00', NOW()),
(2, 'zeus',       'zeus@godsforum.test',      '$2y$12$igiv17wH96tOtz6m7EOP3OYFLfD8HKqmDK.aDKWYsA4I7EAHSjYae', 'admin',     'parchment', 'Founder of the hall. Keep it civil.',        6, '2024-01-04 09:00:00', NOW()),
(3, 'athena',     'athena@godsforum.test',    '$2y$12$igiv17wH96tOtz6m7EOP3OYFLfD8HKqmDK.aDKWYsA4I7EAHSjYae', 'moderator', 'midnight',  'Read the rules before you post.',            5, '2024-01-06 11:20:00', NOW()),
(4, 'hermes',     'hermes@godsforum.test',    '$2y$12$igiv17wH96tOtz6m7EOP3OYFLfD8HKqmDK.aDKWYsA4I7EAHSjYae', 'member',    'slate',     'Fast replies, faster typos.',                4, '2024-02-11 15:45:00', NOW()),
(5, 'hestia',     'hestia@godsforum.test',    '$2y$12$igiv17wH96tOtz6m7EOP3OYFLfD8HKqmDK.aDKWYsA4I7EAHSjYae', 'member',    'parchment', 'Long time lurker.',                          3, '2024-03-02 08:10:00', NOW()),
(6, 'prometheus', 'prometheus@godsforum.test','$2y$12$igiv17wH96tOtz6m7EOP3OYFLfD8HKqmDK.aDKWYsA4I7EAHSjYae', 'member',    'ember',     'Bringing you the good stuff since forever.', 3, '2024-05-19 19:30:00', NOW());

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `position`) VALUES
(1, 'The Great Hall',  'the-great-hall',  'Announcements, introductions and everything about the board itself.', 1),
(2, 'Open Discussion', 'open-discussion', 'General conversation on any subject worth arguing about.',            2),
(3, 'Workshop',        'workshop',        'Craft, code, hardware and the things members build.',                 3);

INSERT INTO `boards` (`id`, `category_id`, `name`, `slug`, `description`, `icon`, `position`) VALUES
(1, 1, 'Announcements',      'announcements',      'Official notices from the staff. Read only for members.', 'campaign',    1),
(2, 1, 'Introductions',      'introductions',      'New here? Tell the hall who you are.',                    'waving_hand', 2),
(3, 1, 'Rules and Feedback', 'rules-and-feedback', 'How the board is run, and how it could be run better.',   'gavel',       3),
(4, 2, 'General Talk',       'general-talk',       'Anything that does not fit anywhere else.',               'forum',       1),
(5, 2, 'Debate Chamber',     'debate-chamber',     'Long form arguments. Sources appreciated.',               'balance',     2),
(6, 2, 'Off Topic',          'off-topic',          'Light conversation, small talk and daily nonsense.',      'coffee',      3),
(7, 3, 'Code and Scripts',   'code-and-scripts',   'Programming questions, snippets and reviews.',            'code',        1),
(8, 3, 'Hardware Corner',    'hardware-corner',    'Machines old and new, repairs and restorations.',         'memory',      2),
(9, 3, 'Design Gallery',     'design-gallery',     'Show the things you have designed and drawn.',            'palette',     3);

INSERT INTO `topics` (`id`, `board_id`, `user_id`, `title`, `slug`, `is_pinned`, `is_locked`, `view_count`, `reply_count`, `created_at`, `last_post_at`) VALUES
(1, 1, 2, 'Welcome to GodsForum',                     'welcome-to-godsforum',                     1, 0, 842, 2, '2024-01-04 09:15:00', '2024-06-11 10:05:00'),
(2, 3, 3, 'Board rules, in short',                    'board-rules-in-short',                     1, 1, 611, 0, '2024-01-06 12:00:00', '2024-01-06 12:00:00'),
(3, 2, 4, 'Hermes reporting in',                      'hermes-reporting-in',                      0, 0, 148, 1, '2024-02-11 16:00:00', '2024-02-12 09:40:00'),
(4, 4, 5, 'What does everyone drink while posting?',  'what-does-everyone-drink-while-posting',   0, 0, 233, 2, '2024-03-02 08:30:00', '2024-06-01 21:12:00'),
(5, 7, 6, 'Prepared statements: a short reminder',    'prepared-statements-a-short-reminder',     0, 0, 512, 1, '2024-05-19 20:00:00', '2024-05-20 07:25:00'),
(6, 5, 2, 'Are old school forums better than feeds?', 'are-old-school-forums-better-than-feeds',  0, 0, 407, 1, '2024-06-10 18:00:00', '2024-06-11 09:55:00');

INSERT INTO `posts` (`id`, `ref`, `topic_id`, `user_id`, `body`, `ip_address`, `created_at`) VALUES
(1, 'rf6u4goctnid', 1, 2, 'This hall is open. GodsForum is a plain, fast message board with threads, boards and members. No infinite scroll, no algorithm deciding what you read. Post something worth reading and treat other members the way you would like to be treated.', '127.0.0.1', '2024-01-04 09:15:00'),
(2, 'ocanknvcgzds', 1, 3, 'Pinned and locked topics will always be marked in the topic list. If you need staff attention, use the report link under any post.', '127.0.0.1', '2024-01-08 10:30:00'),
(3, 'h1bcd7ygdl6m', 1, 6, 'Good to see a board that still looks like a board. The layout loads instantly on an old laptop, which is more than I can say for most sites.', '127.0.0.1', '2024-06-11 10:05:00'),
(4, '9fr19n6ecoz9', 2, 3, 'One: stay on topic in the board you are posting in. Two: no personal attacks. Three: no advertising. Four: search before opening a duplicate thread. Five: staff decisions can be discussed politely in Rules and Feedback.', '127.0.0.1', '2024-01-06 12:00:00'),
(5, 'uu6zclgq7n76', 3, 4, 'Hello everyone. I mostly read, occasionally write, and I am here for the workshop boards. Nice to meet the hall.', '127.0.0.1', '2024-02-11 16:00:00'),
(6, 'r5k425zybfb9', 3, 5, 'Welcome in. The Design Gallery is quiet lately, feel free to wake it up.', '127.0.0.1', '2024-02-12 09:40:00'),
(7, 'yyf9z3s0mt0c', 4, 5, 'Tea, always. Strong, no sugar, and a second cup halfway through a long reply. What is everyone else running on?', '127.0.0.1', '2024-03-02 08:30:00'),
(8, '8jonehddfbsh', 4, 4, 'Coffee before noon, water after. I learned the hard way that four coffees produce four bad posts.', '127.0.0.1', '2024-03-02 12:15:00'),
(9, 'h8tu7gq7fsz8', 4, 6, 'Nothing at all. I type faster with both hands free.', '127.0.0.1', '2024-06-01 21:12:00'),
(10, '81j6rfsvphol', 5, 6, 'Short reminder for anyone writing PHP against MySQL: never place user input inside a query string. Prepare the statement with placeholders and bind the values, and the database engine will treat them as data instead of code. That single habit removes an entire class of vulnerabilities.', '127.0.0.1', '2024-05-19 20:00:00'),
(11, '7bz2gdkwei7z', 5, 2, 'Worth pinning eventually. Add to that: escape everything on output, and put a CSRF token on every form that changes state.', '127.0.0.1', '2024-05-20 07:25:00'),
(12, '4nah8bp14zgw', 6, 2, 'A feed decides what you see. A board lets you decide. The trade is discovery for control, and after a decade of feeds I would take control every time. Where does the hall stand?', '127.0.0.1', '2024-06-10 18:00:00'),
(13, 'vrhpyjrj511a', 6, 3, 'Boards win on memory. A thread from 2009 is still where you left it, still readable, still searchable. Feeds forget everything after a day.', '127.0.0.1', '2024-06-11 09:55:00');

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name',        'GodsForum'),
('site_tagline',     'A meeting hall for mortals with opinions'),
('welcome_message',  'A plain, fast message board. Threads stay where you left them.'),
('registration_open','1'),
('maintenance_mode', '0'),
('default_theme',    'parchment'),
('allow_user_themes','1');

-- Keep the denormalised counters consistent with the seeded rows.
UPDATE `topics` t
   SET t.reply_count = (SELECT COUNT(*) FROM `posts` p WHERE p.topic_id = t.id) - 1,
       t.last_post_at = (SELECT MAX(p.created_at) FROM `posts` p WHERE p.topic_id = t.id);

UPDATE `users` u
   SET u.post_count = (SELECT COUNT(*) FROM `posts` p WHERE p.user_id = u.id);
