# Letter Approval System

A web-based application for managing letter approval workflows, built with PHP and MySQL. This system allows users to create, track, and manage approval requests for letters, with an admin panel for oversight and user management.

## Features

- **User Authentication**: Secure login and registration system with session management.
- **Request Creation**: Create approval requests with multiple file uploads, including drag-and-drop functionality.
- **Approval Workflow**: Define custom approval chains with multiple approvers.
- **Request Tracking**: Public tracking interface to monitor request status and next approvers.
- **Admin Panel**: Manage users, view and manage all requests.
- **File Management**: Support for multiple file uploads with drag-and-drop, stored in the `uploads/` directory.
- **Responsive UI**: Modern, responsive design with enhanced user experience, including navigation buttons, back buttons, and optimized layouts.
- **Status Management**: Standardized status displays (e.g., "Completed" for approved requests).

## Technologies Used

- **Backend**: PHP 7+
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript
- **Server**: Apache (via XAMPP)
- **Additional Libraries**: None (vanilla implementation)

## Installation

### Prerequisites
- XAMPP (or similar Apache, MySQL, PHP stack)
- PHP 7.0 or higher
- MySQL 5.6 or higher
- Web browser (Chrome, Firefox, etc.)

### Setup Steps
1. **Clone or Download the Project**:
   - Place the project files in your XAMPP `htdocs` directory (e.g., `C:\xampp\htdocs\letter_approval_system`).

2. **Database Setup**:
   - Start XAMPP and ensure Apache and MySQL are running.
   - Open phpMyAdmin (http://localhost/phpmyadmin).
   - Create a new database (e.g., `letter_approval_system`).
   - Import the database schema from `includes/db.php` or create tables manually based on the code:
     - `users` table: id, username, password, role, etc.
     - `requests` table: id, user_id, title, description, status, files, workflow, etc.
     - `approvals` table: id, request_id, approver_id, status, etc.
   - Update `includes/db.php` with your database credentials.

3. **File Permissions**:
   - Ensure the `uploads/` directory is writable by the web server (chmod 755 or equivalent on Windows).

4. **Access the Application**:
   - Open your browser and navigate to `http://localhost/letter_approval_system`.
   - Register a new user or log in with existing credentials.
   - For admin access, ensure a user has admin role in the database.

## Usage

### For Users
- **Register/Login**: Create an account or log in.
- **Create Request**: Fill out the form, upload files (drag-and-drop supported), and define the approval workflow.
- **Track Request**: Use the tracking form on the dashboard or public tracking page to check status.
- **View Profile**: Update personal information.

### For Admins
- **Manage Users**: Add, edit, or delete users.
- **Manage Requests**: View all requests, approve/reject, and manage workflows.

### Navigation
- Use the navigation arrows and back buttons for easy movement between pages.
- The footer displays copyright information with a personalized message.

## UI/UX Improvements

- Standardized status texts for clarity.
- Global navigation with arrows and back buttons.
- Enhanced file upload with drag-and-drop and multiple file support.
- Responsive layouts optimized for various screen sizes.
- Modern styling with gradients, animations, and professional appearance.

## Contributing

Developed by Suraj and Sandeep with ❤️.

## License

This project is for educational purposes. Please ensure compliance with your local laws and regulations.