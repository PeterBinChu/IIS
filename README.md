# IIS Project

## Overview
This project provides an equipment and studio management system. It includes functionalities such as user authentication, equipment management, studio management, and reservation handling.

## File Structure

```
.
├── database.sql                 # Database schema and initial data
├── doc.html                     # Project documentation
├── README.md                    # Project README
└── src
    ├── assign_users_to_studio.php
    ├── borrowings.php
    ├── create_equipment.php
    ├── db_connection.php        # Database connection handler
    ├── edit_equipment.php
    ├── edit_profile.php
    ├── edit_reservations.php
    ├── edit_studio.php
    ├── equipment_page.php
    ├── index.php                # Entry point
    ├── login.php
    ├── logout.php
    ├── main_page.php            # User dashboard
    ├── manage_device_restrictions.php
    ├── manage_equipment_types.php
    ├── manage_studio_users.php
    ├── manage_studios.php
    ├── my_equipment.php
    ├── register.php
    ├── reservations.php
    ├── session_timeout.php      # Session timeout logic
    └── view_equipment.php
```

## Setup Instructions

### Requirements
- PHP
- MySQL/MariaDB
- Web Server (e.g., Apache)

### Installation

1. **Clone the repository:**
   ```bash
   git clone <repository-url>
   ```

2. **Database Setup:**
   Import the provided SQL schema (`database.sql`) into your MySQL/MariaDB:
   ```bash
   mysql -u username -p database_name < database.sql
   ```

3. **Configure Database Connection:**
   Edit `src/db_connection.php` to match your database credentials.

4. **Run the Application:**
   Move the contents of the `src` directory to your web server's root folder.

   Access the application via:
   ```
   http://localhost/index.php
   ```

## Usage

- **Registration & Authentication:** Users can register (`register.php`) and log in (`login.php`).
- **Equipment Management:** Admin users can add, edit, and manage equipment.
- **Studio Management:** Admin users can create studios, assign users, and manage studio restrictions.
- **Reservations:** Users can make reservations for equipment and studios.

## Documentation

For detailed project documentation and features overview, please view [doc.html](./doc.html).

## Authors
- **Damir Amankulov** – `xamank00`
- **Daria Kinash** – `xkinas00`

## License

This project is part of an academic course and is intended for educational use only.
