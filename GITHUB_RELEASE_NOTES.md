# Rey CRM System v1.5.2.1

## Latest Release Highlights

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
