ALTER TABLE media_packages
MODIFY COLUMN status ENUM('pending', 'available', 'sold', 'rejected', 'deleted') NOT NULL DEFAULT 'pending';
