CREATE TABLE `api_request_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bot_id` int(11) NOT NULL,
  `method` varchar(255) NOT NULL,
  `request_payload` text,
  `response_payload` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `bot_id` (`bot_id`),
  CONSTRAINT `api_request_logs_ibfk_1` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
