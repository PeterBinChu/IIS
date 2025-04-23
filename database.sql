CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('administrator', 'studio_manager', 'instructor', 'registered_user', 'unregistered_user') NOT NULL
);

CREATE TABLE atelier (
    atelier_id INT PRIMARY KEY AUTO_INCREMENT,
    atelier_name VARCHAR(255) NOT NULL,
    manager_id INT,
    FOREIGN KEY (manager_id) REFERENCES users(user_id)
);

CREATE TABLE equipment_types (
    type_id INT PRIMARY KEY AUTO_INCREMENT,
    type_name VARCHAR(255) NOT NULL
);

CREATE TABLE equipment (
    equipment_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    year_of_manufacture YEAR NOT NULL,
    max_borrow_duration INT,
    pickup_location VARCHAR(255),
    available_hours VARCHAR(255),
    owner_id INT,
    type_id INT,
    FOREIGN KEY (owner_id) REFERENCES users(user_id),
    FOREIGN KEY (type_id) REFERENCES equipment_types(type_id),
    image_data LONGTEXT NOT NULL,
    status ENUM('available', 'prohibited') NOT NULL,
    atelier_id INT NOT NULL,
    FOREIGN KEY (atelier_id) REFERENCES atelier(atelier_id)
);

CREATE TABLE reservations (
    reservation_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    equipment_id INT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('pending', 'approved', 'canceled') NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id)
);

CREATE TABLE loans (
    loan_id INT PRIMARY KEY AUTO_INCREMENT,
    reservation_id INT,
    user_id INT,
    equipment_id INT,
    pickup_date DATE NOT NULL,
    return_date DATE,
    loan_status ENUM('active', 'returned', 'overdue'),
    FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id)
);

CREATE TABLE studio_user_permissions (
    permission_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    atelier_id INT,
    access_level ENUM('Registered', 'Teacher', 'Atelier_Manager', 'Admin'),
    loan_restrictions ENUM('No_Restrictions', 'Specific_Users'),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (atelier_id) REFERENCES atelier(atelier_id)
);

CREATE TABLE equipment_pictures (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    base64_data LONGTEXT NOT NULL
);

CREATE TABLE device_user_restrictions (
    restriction_id INT PRIMARY KEY AUTO_INCREMENT,
    equipment_id INT NOT NULL,
    user_id INT NOT NULL,
    FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);