-- Air DS — sample data
-- Run after schema.sql:  mysql -u root air_ds < sql/seed.sql

USE `air_ds`;

-- Airports.
INSERT INTO `airports` (`name`, `code`, `latitude`, `longitude`, `tax`) VALUES
  ('Athens International Airport "Eleftherios Venizelos"', 'ATH', 37.937225, 23.945238, 150.00),
  ('Paris Charles de Gaulle Airport',                      'CDG', 49.009724,  2.547778, 200.00),
  ('Leonardo da Vinci Rome Fiumicino Airport',             'FCO', 41.810800, 12.250900, 150.00),
  ('Adolfo Suárez Madrid–Barajas Airport',                 'MAD', 40.489500, -3.564300, 250.00),
  ('Larnaka International Airport',                        'LCA', 34.871500, 33.607700, 150.00),
  ('Brussels Airport',                                     'BRU', 50.900200,  4.485900, 200.00);

-- Demo user. Username: demouser — Password: Demo1234
INSERT INTO `users` (`first_name`, `last_name`, `username`, `password`, `email`) VALUES
  ('Demo', 'User', 'demouser',
   '$2y$10$SWfuEW5j.m9nvesm.UUPhOoFU6mQPrkUkrpthHLM5R0AQiqdydadu',
   'demo@airds.com');

-- A couple of sample reservations for the demo user.
INSERT INTO `reservations`
  (`user_id`, `departure_airport_id`, `arrival_airport_id`,
   `flight_date`, `passengers_count`, `seats`, `total_cost`) VALUES
  (1, 1, 2, DATE_ADD(CURDATE(), INTERVAL 60 DAY), 2, '4A,4B', 951.94),
  (1, 3, 5, DATE_ADD(CURDATE(), INTERVAL 10 DAY), 1, '1C',    445.30);
