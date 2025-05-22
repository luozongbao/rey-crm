# Rey CRM System Release Notes

## Version 1.6.0 (2023-05-23)

### 🌍 Improved Timezone Handling
- **Client-side Timezone Processing**
  - Automatic browser timezone detection
  - No server configuration needed
  - Seamless timezone conversion
- **Consistent DateTime Display**
  - 24-hour time format support
  - UTC-based storage
  - Local time display everywhere
- **Enhanced User Experience**
  - Automatic timezone conversion
  - No more timezone configuration needed

### 🐳 Docker Support
- **Complete Containerization**
  - Nginx web server container
  - PHP 8.3-FPM container
  - MariaDB database container
  - Docker Compose orchestration
- **Development Features**
  - Volume mounting for live updates
  - Log persistence
  - Easy environment configuration
- **Deployment Improvements**
  - One-command deployment
  - Consistent environments
  - Built-in composer installation
  - Automatic dependency management

### 🔧 Technical Improvements
- Removed legacy timezone settings
- Optimized datetime handling
- Improved code organization
- Enhanced development workflow

### 🐛 Bug Fixes
- Fixed timezone inconsistencies
- Resolved AM/PM format issues
- Improved date validation

## Previous Versions

# Rey CRM System v1.5.3 - Release Notes

**Release Date:** May 21, 2025  
**Initial GitHub Release:** No  
**Version:** 1.5.3  

## Overview

Rey CRM System is a modern PHP-based Customer Relationship Management (CRM) solution designed for managing customer interactions, contacts, and activity history. This release introduces a dark mode toggle and improved settings page layout.

## Changes in v1.5.3

### New Features
- Added dark mode toggle in the header for instant theme switching
- Arranged settings page cards for a cleaner, more organized layout

## Known Issues
- Very large data exports (>10,000 records) may time out on some server configurations
- Internet Explorer 11 has limited support for some UI features
- Some visual elements may not render correctly on very small screens (<320px)

## Upgrade Instructions

For users upgrading from v1.5.2.1:

1. Back up your database and files
2. Download the latest release
3. Replace all files except `includes/config.php`
4. Run any pending database migrations

## Credits

Rey CRM System is developed and maintained by VIBE Coding.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

---

Thank you for using Rey CRM System! We welcome your feedback and contributions.
