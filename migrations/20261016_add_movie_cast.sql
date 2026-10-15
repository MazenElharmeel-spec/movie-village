USE movies_village;

CREATE TABLE IF NOT EXISTS movie_cast (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  movie_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  photo_path VARCHAR(255) NULL,
  position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY movie_cast_order (movie_id, position),
  CONSTRAINT movie_cast_movie_fk FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO movie_cast (movie_id, name, photo_path, position)
SELECT id, 'Dwayne Johnson', 'play-page/cast1.jpg', 0 FROM movies WHERE title = 'Jumanji: Welcome to the Jungle';
INSERT IGNORE INTO movie_cast (movie_id, name, photo_path, position)
SELECT id, 'Karen Gillan', 'play-page/cast2.jpg', 1 FROM movies WHERE title = 'Jumanji: Welcome to the Jungle';
INSERT IGNORE INTO movie_cast (movie_id, name, photo_path, position)
SELECT id, 'Kevin Hart', 'play-page/cast3.jpg', 2 FROM movies WHERE title = 'Jumanji: Welcome to the Jungle';
INSERT IGNORE INTO movie_cast (movie_id, name, photo_path, position)
SELECT id, 'Jack Black', 'play-page/cast4.jpg', 3 FROM movies WHERE title = 'Jumanji: Welcome to the Jungle';
INSERT IGNORE INTO movie_cast (movie_id, name, photo_path, position)
SELECT id, 'Nick Jonas', 'play-page/cast5.jpg', 4 FROM movies WHERE title = 'Jumanji: Welcome to the Jungle';
INSERT IGNORE INTO movie_cast (movie_id, name, photo_path, position)
SELECT id, 'Madison Iseman', 'play-page/cast6.jpg', 5 FROM movies WHERE title = 'Jumanji: Welcome to the Jungle';
