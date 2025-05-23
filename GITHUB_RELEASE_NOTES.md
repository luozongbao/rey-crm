# Rey CRM System v1.6.2

## Major Updates in This Release

### 🕒 Time Handling Enhancements
- **Timezone Handling Optimization**
  - Removed redundant UTC conversion in frontend
  - Fixed double timezone conversion issue
  - Streamlined datetime handling across forms
- **Form DateTime Management**
  - Improved history form datetime handling
  - Enhanced follow-up datetime pickers
  - Consistent timezone display

### 💻 Technical Details

#### DateTime Handling Implementation
```javascript
// Updated datetime conversion (removed redundant UTC conversion)
document.querySelectorAll('.datetime').forEach(element => {
    if (element.tagName === 'TD' || element.tagName === 'TH') {
        const utcDate = element.textContent.trim();
        if (utcDate && utcDate !== 'N/A') {
            const date = new Date(utcDate); // No 'Z' suffix as dates are already UTC
            element.textContent = formatDateTo24Hour(date);
        }
    }
});
```

#### Database Integration
```sql
-- Database already handles timezone conversion
SELECT CONVERT_TZ(action_datetime, '+00:00', @@session.time_zone) as action_datetime
FROM action_history
```

### 🔒 Contact Management Security
- **Main Contact Protection**
  - Server-side deletion prevention
  - UI-level protection
  - Role-based validation
- **Enhanced Contact Display**
  - Role information in lists
  - Improved visual organization
  - Better contact relationships

### 📊 Data Access Improvements
- **Historical Data Management**
  - Complete follow-up history
  - Full activity timeline
  - Enhanced date filtering
- **Contact Organization**
  - Role-based display
  - Improved contact hierarchy
  - Better data presentation

### 🎨 UI/UX Improvements
- Protected contacts clearly identified
- Enhanced role display
- Improved feedback messages
- Better date range handling

## Installation

### Requirements
- PHP 8.3+
- MySQL/MariaDB
- Web server (Apache/Nginx)
- Composer

### Quick Start
```bash
git clone https://github.com/yourusername/rey-crm.git
cd rey-crm
composer install
```

## Previous Versions

### 📚 Documentation Improvements
- **Updated installation process** - Corrected documentation to reflect automatic configuration setup
- **Streamlined setup guide** - Clearer instructions for first-time installation
- **Improved server configuration** - Enhanced guidance for web server setup
- **Composer integration** - Better documentation of dependency management

### Previous Version (v1.5.2)

#### 🌍 Timezone Support
- **System-wide timezone configuration** - Set your organization's timezone in system settings
- **Smart datetime handling** - All dates are stored in UTC and displayed in your local timezone
- **Consistent display** - Improved datetime formatting across all views and exports
- **Automatic conversion** - Seamless conversion between UTC and local time for all operations

#### 🔄 Data Integrity
- **UTC-based storage** - All datetime data is now stored in UTC for consistency
- **Timezone-aware exports** - CSV exports now respect your timezone settings
- **Migration support** - Tools for handling existing datetime data

#### 📊 Enhanced Views
- **Improved activity history** - Better datetime display in activity lists
- **Follow-up scheduling** - Timezone-aware scheduling and reminders
- **Dashboard updates** - Enhanced datetime display in analytics and reports

#### 🛠️ Technical Improvements
- New timezone helper functions for developers
- Enhanced form handling for datetime inputs
- Improved data consistency across the system

## Previous Release (v1.5.0)

This is the first public GitHub release of Rey CRM System, a modern PHP-based Customer Relationship Management solution.

## What's New in v1.5.0

### Email System Integration
- Complete SMTP email configuration
- Email testing functionality
- Customizable sender name and email
- Support for TLS/SSL encryption

### Enhanced Security
- Self-service password reset functionality
- Time-limited, single-use reset tokens
- Secure email-based verification
- Rate limiting to prevent brute force attacks

### User Experience Improvements
- New user profile management page
- Improved form styling and validation
- Real-time password match validation
- Responsive card-based layouts

### Core Features
- Complete customer lifecycle management with status tracking
- Contact person management with role-based organization
- Activity history tracking and follow-up scheduling
- Location-based customer organization with province/country support
- Comprehensive analytics dashboard

### Key Capabilities
- Export activity history and follow-up schedules to CSV
- Custom date range filtering for data analysis
- Color-coded status indicators for visual management
- Advanced search and filtering with state preservation
- Role-based access control for team collaboration

## Technical Requirements

- PHP 7.4+ recommended (7.0+ minimum)
- MySQL 5.7+ or MariaDB 10.2+
- Web server (Apache 2.4+/Nginx 1.14+)
- Modern web browser with JavaScript enabled
- Composer (for PHPMailer installation)

## Quick Start

1. Clone the repository:
   ```bash
   git clone <repository-url>
   cd rey-crm
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Configure web server:
   - Point document root to project directory
   - Ensure write permissions for logs/

4. Run installation:
   - Visit `http://your-domain/includes/install.php`
   - Follow the setup wizard
   - Configuration file will be created automatically

5. Post-installation:
   - Log in with admin credentials
   - Set your timezone
   - Configure SMTP settings
   - Add users as needed

## Documentation

- [Email System](docs/email_functionality.md)
- [Password Reset](docs/password_reset.md)

---

For a detailed changelog, please see [RELEASE.md](RELEASE.md).

Rey CRM System is licensed under the [MIT License](LICENSE).
