SET FOREIGN_KEY_CHECKS=0;

-- Import class data first (no dependencies)
INSERT INTO class (class_id, class_name) VALUES
(9,'Class 9'),(10,'Class 10'),(11,'Class 11'),(12,'Class 12');

-- Import book data
INSERT INTO book (book_id, class_id, book_name) VALUES
(1,9,'Biology'),(7,9,'Physics'),(9,9,'Computer'),(164,9,'Chemistry'),
(1001,10,'Physics'),(1005,10,'Biology'),(1006,10,'Computer');

-- Import chapter data
INSERT INTO chapter (chapter_id, chapter_name, chapter_no, class_id, book_id, book_name) VALUES
(1,'The Science of Biology',1,9,1,'Biology'),
(2,'Biodiversity',2,9,1,'Biology'),
(3,'The Cell',3,9,1,'Biology'),
(4,'Cell Cycle',4,9,1,'Biology'),
(5,'Tissues, Organs, and Organ Systems',5,9,1,'Biology'),
(6,'Biomolecules',6,9,1,'Biology'),
(7,'Enzymes',7,9,1,'Biology'),
(8,'Bioenergetics',8,9,1,'Biology'),
(9,'Plant Physiology',9,9,1,'Biology'),
(103,'Chapter 1',1,10,1001,'Physics'),
(104,'Chapter 2',2,10,1001,'Physics'),
(105,'Chapter 3',3,10,1001,'Physics'),
(106,'Chapter 4',4,10,1001,'Physics'),
(107,'Chapter 5',5,10,1001,'Physics'),
(108,'Chapter 6',6,10,1001,'Physics'),
(109,'Chapter 7',7,10,1001,'Physics'),
(110,'Chapter 8',8,10,1001,'Physics'),
(111,'Chapter 9',9,10,1001,'Physics'),
(118,'Chapter 10',1,10,1005,'Biology'),
(121,'Chapter 11',2,10,1005,'Biology'),
(126,'Chapter 12',3,10,1005,'Biology'),
(127,'Chapter 13',4,10,1005,'Biology'),
(128,'Chapter 14',5,10,1005,'Biology'),
(129,'Chapter 15',6,10,1005,'Biology'),
(130,'Chapter 16',7,10,1005,'Biology'),
(131,'Chapter 17',8,10,1005,'Biology'),
(132,'Chapter 18',9,10,1005,'Biology'),
(133,'Chapter1',1,10,1006,'Computer');

-- Import admin data
INSERT INTO admins (id, name, email, password, role, created_at) VALUES
(1,'Touseef','touseef12345bhatti@gmail.com','123','superadmin','2025-08-12 10:28:11'),
(2,'Arshad Bhatti','arshad@gmail.com','Arshad@321','admin','2025-08-15 16:14:28');

-- Import users data
INSERT INTO users (id, name, email, google_id, oauth_provider, password, created_at, token, verified, subscription_expires_at, subscription_status, role) VALUES
(1,'touseef','touseef12345bhatti@gmail.com',NULL,'local','$2y$10$8M0enYiAoQZqcFNFElIpm.qWx7Cla6EwKDMEe7sV/3bd2lL0eBabu','2025-08-12 10:44:28',NULL,0,'2025-09-30 16:06:05','premium','super_admin'),
(2,'aa','a@a',NULL,'local','$2y$10$chAyBxNT3.N2KVuED3LHheUkpR3pvsP8vY3BLMK2ad7aFR/QoPLtS','2025-08-12 10:47:35',NULL,0,NULL,'free','user');

-- Import contact_messages data
INSERT INTO contact_messages (id, name, email, message, status, user_agent, ip_address, created_at, updated_at) VALUES
(1,'touseef bhatti','touseef12345bhatti@gmail.com','asnckasn','unread','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36','::1','2025-09-09 16:51:56','2025-09-09 16:51:56');

SET FOREIGN_KEY_CHECKS=1;