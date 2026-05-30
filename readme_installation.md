# गुनासो व्यवस्थापन प्रणाली - बेसीशहर नगरपालिका
# Complaint Management System - Besishahar Municipality

A comprehensive complaint tracking and management system built with PHP, MySQL, and Bootstrap for Besishahar Municipality.

## 🌟 Features

### Public Features
- **गुनासो दर्ता (Complaint Registration)**: Citizens can submit complaints with personal details, documents, and descriptions
- **गुनासो ट्र्याकिङ (Complaint Tracking)**: Track complaint status using unique tracking ID
- **Real-time Status Updates**: View complete history and admin responses
- **Email Notifications**: Automatic email alerts on status changes
- **File Upload**: Support for images, PDFs, and documents (up to 5MB)

### Admin Panel Features
- **Dashboard**: Statistics and overview of all complaints
- **Complaint Management**: View, filter, search, and manage all complaints
- **Employee Management**: Add, edit, and manage employees
- **Branch Management**: Manage different departments/branches
- **Complaint Type Management**: Define and manage complaint categories
- **Assignment System**: Assign complaints to specific employees
- **Status Management**: Update complaint status with remarks and replies
- **Priority System**: Set complaint priority levels
- **Reports**: Generate reports and analytics
- **Settings**: Configure system settings

### Employee Panel Features
- **View Assigned Complaints**: See only complaints assigned to them
- **Update Status**: Change status and add remarks
- **Track Progress**: Monitor complaint progress

## 🛠️ Technology Stack

- **Frontend**: HTML5, CSS3, Bootstrap 5.3, Bootstrap Icons
- **Backend**: PHP 7.4+ (Core PHP)
- **Database**: MySQL 5.7+
- **Server**: Apache (XAMPP/WAMP compatible)

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache Web Server
- 50MB minimum disk space
- Web browser (Chrome, Firefox, Edge recommended)

## 🚀 Installation Instructions

### Step 1: Download and Extract

1. Download all the system files
2. Extract to your web server directory:
   - XAMPP: `C:/xampp/htdocs/gunaso_system`
   - WAMP: `C:/wamp64/www/gunaso_system`

### Step 2: Database Setup

1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Create a new database named `besishahar_gunaso`
3. Import the SQL file:
   - Click on the database
   - Go to "Import" tab
   - Choose `database_schema.sql` file
   - Click "Go"

**OR** Run the SQL commands directly:
```sql
-- Copy and paste the entire content from database_schema.sql
```

### Step 3: Configuration

1. Open `config.php` in a text editor
2. Update database credentials if needed:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Your MySQL password
define('DB_NAME', 'besishahar_gunaso');
```

3. Update site URL:
```php
define('SITE_URL', 'http://localhost/gunaso_system');
```

### Step 4: Set Permissions

Create the uploads directory and set permissions:
```bash
mkdir uploads
chmod 755 uploads
```

For Windows: Right-click `uploads` folder → Properties → Security → Edit → Allow Write permissions

### Step 5: Access the System

**Public Side:**
- Homepage: `http://localhost/gunaso_system/`
- Submit Complaint: `http://localhost/gunaso_system/submit.php`
- Track Complaint: `http://localhost/gunaso_system/track.php`

**Admin Panel:**
- Login: `http://localhost/gunaso_system/admin/login.php`
- Default Credentials:
  - Username: `admin`
  - Password: `password`

**⚠️ IMPORTANT: Change the default password immediately after first login!**

## 📁 File Structure

```
gunaso_system/
│
├── config.php                 # Database configuration
├── index.php                  # Homepage
├── submit.php                 # Complaint submission form
├── track.php                  # Complaint tracking page
│
├── admin/
│   ├── login.php             # Admin login
│   ├── logout.php            # Logout
│   ├── dashboard.php         # Dashboard
│   ├── complaints.php        # All complaints
│   ├── complaint_detail.php  # Complaint details & update
│   ├── employees.php         # Employee management
│   ├── branches.php          # Branch management
│   ├── types.php             # Complaint type management
│   ├── reports.php           # Reports
│   ├── settings.php          # System settings
│   └── profile.php           # User profile
│
├── uploads/                   # Uploaded files directory
│
└── assets/                    # Images and static files
    └── logo.png              # Municipality logo
```

## 🔐 Security Features

- Password hashing using PHP's `password_hash()`
- SQL injection prevention using prepared statements
- XSS protection using `htmlspecialchars()`
- Input sanitization
- Session management
- File upload validation
- Role-based access control (Admin/Employee)

## 📧 Email Configuration (Optional)

To enable email notifications, you need to configure email settings:

1. Open `config.php`
2. Add SMTP configuration:
```php
// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('FROM_EMAIL', 'no-reply@besishahar.gov.np');
define('FROM_NAME', 'बेसीशहर नगरपालिका');
```

3. For Gmail, you need to:
   - Enable 2-factor authentication
   - Generate an App Password
   - Use the App Password in configuration

## 🎨 Customization

### Change Logo
Replace `assets/logo.png` with your municipality logo (recommended size: 200x200px)

### Change Colors
Edit CSS variables in each file:
```css
:root {
    --primary-color: #1e40af;  /* Change this */
    --secondary-color: #dc2626;
}
```

### Add More Branches
Go to Admin Panel → शाखा व्यवस्थापन → Add new branches

### Add Complaint Types
Go to Admin Panel → गुनासो प्रकार → Add new types

## 📊 Default Data

The system comes with pre-populated data:

**Branches:**
- प्रशासन शाखा (Administration)
- योजना शाखा (Planning)
- शिक्षा शाखा (Education)
- स्वास्थ्य शाखा (Health)
- पूर्वाधार शाखा (Infrastructure)
- सामाजिक विकास शाखा (Social Development)
- राजस्व शाखा (Revenue)
- वन तथा वातावरण शाखा (Forest & Environment)

**Complaint Types:**
- सेवा सम्बन्धी (Service Related)
- कर्मचारी व्यवहार (Staff Behavior)
- भौतिक पूर्वाधार (Physical Infrastructure)
- सार्वजनिक सेवा (Public Service)
- भ्रष्टाचार (Corruption)
- वातावरण (Environment)
- योजना कार्यान्वयन (Project Implementation)
- अन्य (Others)

## 🐛 Troubleshooting

### Database Connection Error
- Check if MySQL is running
- Verify database credentials in `config.php`
- Ensure database exists

### File Upload Error
- Check `uploads/` directory exists
- Verify write permissions on `uploads/` folder
- Check PHP `upload_max_filesize` and `post_max_size` settings

### Session Error
- Ensure PHP sessions are enabled
- Check session directory permissions
- Clear browser cookies

### Page Not Found (404)
- Verify `.htaccess` file exists (if using mod_rewrite)
- Check file paths and URLs
- Ensure AllowOverride is enabled in Apache config

## 📝 Usage Guide

### For Citizens (नागरिकहरूका लागि)

1. **गुनासो दर्ता गर्न (To Submit Complaint):**
   - Go to homepage
   - Click "गुनासो दर्ता गर्नुहोस्"
   - Fill in all required fields
   - Upload supporting documents (optional)
   - Submit the form
   - **IMPORTANT**: Save your tracking ID

2. **गुनासो ट्र्याक गर्न (To Track Complaint):**
   - Go to "गुनासो ट्र्याक गर्नुहोस्"
   - Enter your tracking ID
   - View status and updates

### For Admin/Employees (प्रशासक/कर्मचारीहरूका लागि)

1. **Login:**
   - Go to admin login page
   - Enter username and password
   - Access dashboard

2. **Manage Complaints:**
   - View all complaints or assigned complaints
   - Click on complaint to see details
   - Update status, priority, and add remarks
   - Assign to employees (Admin only)
   - Provide reply to citizens

3. **Manage System:**
   - Add/edit employees
   - Manage branches and complaint types
   - View reports and statistics
   - Configure system settings

## 🔄 Updates and Maintenance

### Backup Database
Regularly backup your database:
```bash
mysqldump -u root -p besishahar_gunaso > backup_$(date +%Y%m%d).sql
```

### Update System
1. Backup database and files
2. Replace files with new version
3. Run any database migration scripts
4. Test functionality

## 📞 Support

For issues or questions:
- Email: info@besishahar.gov.np
- Phone: ०६५-५६०३२२

## 📄 License

This system is developed for बेसीशहर नगरपालिका.
All rights reserved © 2025

## 🙏 Credits

Developed for better governance and citizen service delivery.

---

**Version:** 1.0  
**Last Updated:** October 2025  
**Developed for:** बेसीशहर नगरपालिका, लमजुङ, नेपाल