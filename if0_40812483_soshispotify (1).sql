SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `album` (
  `album_id` varchar(50) NOT NULL,
  `album_name` varchar(255) NOT NULL,
  `release_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `img_64` varchar(512) DEFAULT NULL,
  `img_300` varchar(512) DEFAULT NULL,
  `img_640` varchar(512) DEFAULT NULL,
  `type` varchar(25) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `album_artist` (
  `album_id` varchar(50) NOT NULL,
  `artist_id` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `album_track` (
  `album_id` varchar(50) NOT NULL,
  `track_id` varchar(50) NOT NULL,
  `track_number` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `artist` (
  `artist_id` varchar(50) NOT NULL,
  `artist_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `artist_stats` (
  `artist_id` varchar(50) NOT NULL,
  `stat_date` date NOT NULL,
  `monthly_listeners` bigint(20) DEFAULT NULL,
  `followers` bigint(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `streams` (
  `track_id` varchar(50) NOT NULL,
  `stream_date` date NOT NULL,
  `stream_count` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `track` (
  `track_id` varchar(50) NOT NULL,
  `track_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `track_artist` (
  `track_id` varchar(50) NOT NULL,
  `artist_id` varchar(50) NOT NULL,
  `artist_role` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


ALTER TABLE `album`
  ADD PRIMARY KEY (`album_id`);

ALTER TABLE `album_artist`
  ADD PRIMARY KEY (`album_id`,`artist_id`),
  ADD KEY `fk_album_artist_artist` (`artist_id`);

ALTER TABLE `album_track`
  ADD PRIMARY KEY (`album_id`,`track_id`),
  ADD KEY `fk_album_track_track` (`track_id`);

ALTER TABLE `artist`
  ADD PRIMARY KEY (`artist_id`);

ALTER TABLE `artist_stats`
  ADD PRIMARY KEY (`artist_id`,`stat_date`);

ALTER TABLE `streams`
  ADD PRIMARY KEY (`track_id`,`stream_date`);

ALTER TABLE `track`
  ADD PRIMARY KEY (`track_id`);

ALTER TABLE `track_artist`
  ADD PRIMARY KEY (`track_id`,`artist_id`),
  ADD KEY `fk_track_artist_artist` (`artist_id`);


ALTER TABLE `album_artist`
  ADD CONSTRAINT `fk_album_artist_album` FOREIGN KEY (`album_id`) REFERENCES `album` (`album_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_album_artist_artist` FOREIGN KEY (`artist_id`) REFERENCES `artist` (`artist_id`) ON DELETE CASCADE;

ALTER TABLE `album_track`
  ADD CONSTRAINT `fk_album_track_album` FOREIGN KEY (`album_id`) REFERENCES `album` (`album_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_album_track_track` FOREIGN KEY (`track_id`) REFERENCES `track` (`track_id`) ON DELETE CASCADE;

ALTER TABLE `artist_stats`
  ADD CONSTRAINT `fk_artist_stats_artist` FOREIGN KEY (`artist_id`) REFERENCES `artist` (`artist_id`) ON DELETE CASCADE;

ALTER TABLE `streams`
  ADD CONSTRAINT `fk_streams_track` FOREIGN KEY (`track_id`) REFERENCES `track` (`track_id`) ON DELETE CASCADE;

ALTER TABLE `track_artist`
  ADD CONSTRAINT `fk_track_artist_artist` FOREIGN KEY (`artist_id`) REFERENCES `artist` (`artist_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_track_artist_track` FOREIGN KEY (`track_id`) REFERENCES `track` (`track_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
