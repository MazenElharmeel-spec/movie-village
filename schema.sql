CREATE DATABASE IF NOT EXISTS movies_village CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE movies_village;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('member', 'admin') NOT NULL DEFAULT 'member',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS movies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  genre VARCHAR(120) NOT NULL DEFAULT 'Uncategorised',
  release_year SMALLINT UNSIGNED NULL,
  rating DECIMAL(3,1) NULL,
  synopsis TEXT NOT NULL,
  cast_names TEXT NULL,
  poster_path VARCHAR(255) NOT NULL,
  backdrop_path VARCHAR(255) NULL,
  video_path VARCHAR(255) NULL,
  trailer_url VARCHAR(500) NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  trending TINYINT(1) NOT NULL DEFAULT 0,
  published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY movies_published (published, created_at),
  KEY movies_trending (trending, published),
  KEY movies_featured (featured, published)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS movie_cast (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  movie_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  photo_path VARCHAR(255) NULL,
  position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY movie_cast_order (movie_id, position),
  CONSTRAINT movie_cast_movie_fk FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS favorites (
  user_id BIGINT UNSIGNED NOT NULL,
  movie_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, movie_id),
  CONSTRAINT favorites_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT favorites_movie_fk FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO movies (title, genre, release_year, rating, synopsis, cast_names, poster_path, backdrop_path, featured, trending, published) VALUES
('Jumanji: Welcome to the Jungle', 'Action · Adventure', 2017, 6.9, 'Four teenagers are pulled into a magical video game and must work together to finish it and find their way home.', 'Dwayne Johnson, Karen Gillan, Kevin Hart, Jack Black, Nick Jonas, Madison Iseman', 'img/movie-1.jpg', 'play-page/play-background.jpg', 1, 1, 1),
('Oppenheimer', 'Drama · History', 2023, 8.3, 'A physicist and his team race to develop the first atomic bomb during World War II.', '', 'img/movie-2.jpg', 'img/home-background.png', 0, 1, 1),
('The Dark Knight', 'Action · Crime', 2008, 9.0, 'Batman faces a criminal mastermind who pushes Gotham into chaos.', '', 'img/movie-3.jpg', 'img/home-background.png', 0, 1, 1),
('Dune: Part Two', 'Sci-fi · Adventure', 2024, 8.5, 'Paul Atreides joins Chani and the Fremen as he seeks revenge and confronts a choice that could change the universe.', '', 'img/movie-4.jpg', 'img/home-background.png', 0, 1, 1),
('The Flash', 'Action · Fantasy', 2023, 6.6, 'Barry Allen uses his speed to change the past, with consequences for the world he returns to.', '', 'img/movie-5.jpg', 'img/home-background.png', 0, 0, 1),
('Interstellar', 'Sci-fi · Adventure', 2014, 8.7, 'A team travels beyond this galaxy to discover whether mankind has a future among the stars.', '', 'img/movie-6.jpg', 'img/home-background.png', 0, 1, 1),
('Inception', 'Sci-fi · Action', 2010, 8.8, 'A skilled thief enters the dreams of others and is offered a chance to return to his family.', '', 'img/movie-7.jpg', 'img/home-background.png', 0, 0, 1),
('Top Gun: Maverick', 'Action · Drama', 2022, 8.2, 'After decades of service, Maverick returns to train a new group of pilots for a dangerous mission.', '', 'img/movie-8.jpg', 'img/home-background.png', 0, 0, 1),
('Spider-Man: No Way Home', 'Action · Adventure', 2021, 8.2, 'Peter Parker asks for help when the world learns his identity, opening a door to unexpected visitors.', '', 'img/popular-movie-1.jpg', 'img/home-background.png', 0, 1, 1),
('Jungle Cruise', 'Action · Adventure', 2021, 6.6, 'A riverboat captain guides a scientist through the jungle in search of a legendary tree.', '', 'img/popular-movie-2.jpg', 'img/home-background.png', 0, 0, 1),
('Loki', 'Action · Fantasy', 2021, 8.2, 'A version of Loki is drawn into a journey through time and the mysteries of the TVA.', '', 'img/popular-movie-3.jpg', 'img/home-background.png', 0, 1, 1),
('Joker', 'Drama · Crime', 2019, 8.4, 'A struggling comedian finds himself on a path that transforms him into a symbol of unrest.', '', 'img/popular-movie-4.jpg', 'img/home-background.png', 0, 0, 1),
('Money Heist', 'Drama · Crime', 2017, 8.2, 'A group of robbers carry out carefully planned heists under the guidance of the Professor.', '', 'img/popular-movie-5.jpg', 'img/home-background.png', 0, 1, 1),
('Breaking Bad', 'Drama · Crime', 2008, 9.5, 'A chemistry teacher turns to manufacturing drugs after receiving a life changing diagnosis.', '', 'img/popular-movie-6.jpg', 'img/home-background.png', 0, 0, 1),
('Green Book', 'Drama · Comedy', 2018, 8.2, 'A pianist and his driver travel through the American South and form an unlikely friendship.', '', 'img/popular-movie-7.jpg', 'img/home-background.png', 0, 0, 1),
('Game of Thrones', 'Drama · Fantasy', 2011, 9.2, 'Families across Westeros compete for power while a much older threat gathers beyond the Wall.', '', 'img/popular-movie-8.jpg', 'img/home-background.png', 0, 1, 1);

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
