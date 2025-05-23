# Rey CRM System Release Notes

## Version 1.6.2 (2025-05-23)

### 🕒 Time Handling Improvements
- **Fixed Timezone Handling**
  - Removed redundant UTC conversion in JavaScript
  - Fixed double timezone conversion issue
  - Improved datetime display across all forms
- **Enhanced DateTime Management**
  - More accurate datetime handling in forms
  - Consistent timezone display in history forms
  - Better handling of follow-up datetime pickers

### 🔒 Enhanced Contact Management
- **Main Contact Protection**
  - Automatic main contact identification
  - Protected deletion of main contacts
  - Improved UI feedback
- **Contact Display Improvements**
  - Role information in contacts list
  - Clearer contact relationships
  - Better contact organization

### 📊 Data Management Improvements
- **Historical Data Access**
  - Full access to past follow-ups
  - Complete activity history
  - Improved date filtering
- **Contact Role Handling**
  - Better role display
  - Enhanced contact organization
  - Improved data consistency

### 🎨 UI/UX Enhancements
- Added role display in contact lists
- Improved contact deletion handling
- Better visual feedback for protected contacts

### 🐛 Bug Fixes
- Fixed historical follow-up data access
- Improved date range filtering in activities
- Enhanced contact management security
- Fixed issue with datetime display in customer forms
- Resolved timezone conversion redundancy
- Improved datetime handling in activity history
- Enhanced datetime picker behavior in history forms

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
