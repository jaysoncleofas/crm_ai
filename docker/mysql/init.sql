-- Tests run against their own schema so they can never touch application data.
CREATE DATABASE IF NOT EXISTS `crm_testing`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `crm_testing`.* TO 'crm'@'%';
FLUSH PRIVILEGES;
