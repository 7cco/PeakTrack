CREATE DATABASE IF NOT EXISTS tracker_db 
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; 

GRANT ALL ON tracker_db.* TO 'odoo'@'%';
FLUSH PRIVILEGES;