# Rey CRM System v1.4.1 - Release Notes

**Release Date:** May 17, 2025  
**Initial GitHub Release:** Yes  
**Version:** 1.4.1  

## Overview

Rey CRM System is a modern PHP-based Customer Relationship Management (CRM) solution designed for managing customer interactions, contacts, and activity history. This is our first public release on GitHub, though the software has been in development and private use through version 1.4.1.

## Features

### Core Functionality
- Complete customer lifecycle management
- Contact person management with multiple contacts per customer
- Comprehensive activity history tracking and follow-up scheduling
- Status-based customer tracking with visual indicators
- Smart location handling with province/country support
- Detailed dashboard with analytics and statistics

### User Experience
- Modern, responsive interface with clean design
- Advanced search and filtering capabilities
- Dynamic status badges with color coding
- Efficient pagination with state preservation
- Form state persistence across navigation

### Security
- Secure authentication system with password hashing
- Protection against SQL injection and XSS attacks
- CSRF protection on all forms
- Role-based access control
- Secure session management

## Technical Details

- **PHP Version:** Requires PHP 7.4+ (7.0+ minimum)
- **Database:** MySQL 5.7+ or MariaDB 10.2+
- **Web Server:** Apache 2.4+/Nginx 1.14+
- **Browser Support:** All modern browsers (Chrome, Firefox, Safari, Edge)

## Installation

Please refer to the [README.md](README.md) file for detailed installation instructions.

## Changes in v1.4.1

### New Features
- Added export capability for activity history to CSV
- Implemented follow-up schedule export to CSV
- Added custom date range filtering for exports
- Enhanced status indicators with improved visual design
- Added user settings page for personalized preferences

### Improvements
- Optimized database queries for better performance
- Enhanced mobile responsiveness for all screens
- Improved form validation with better error messaging
- Updated UI components for better accessibility
- Streamlined navigation between related records

### Bug Fixes
- Fixed pagination issue when returning from detail views
- Corrected date formatting inconsistencies
- Resolved session timeout handling
- Fixed customer filtering by empty location values
- Addressed form submission issues on slower connections

## Known Issues
- Very large data exports (>10,000 records) may time out on some server configurations
- Internet Explorer 11 has limited support for some UI features
- Some visual elements may not render correctly on very small screens (<320px)

## Upgrade Instructions

For users upgrading from private releases:

1. Back up your database and files
2. Download the latest release
3. Replace all files except `includes/config.php`
4. Run any pending database migrations by visiting `includes/update.php`

## Credits

Rey CRM System is developed and maintained by VIBE Coding.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

---

Thank you for using Rey CRM System! We welcome your feedback and contributions.
