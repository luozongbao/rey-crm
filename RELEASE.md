# Rey CRM System v1.5.2 - Release Notes

**Release Date:** May 20, 2025  
**Initial GitHub Release:** No  
**Version:** 1.5.2  

## Overview

Rey CRM System is a modern PHP-based Customer Relationship Management (CRM) solution designed for managing customer interactions, contacts, and activity history. This release adds comprehensive timezone support and improves datetime handling across the system.

## Changes in v1.5.2

### New Features
- Implemented comprehensive timezone support:
  - System-wide timezone configuration in settings
  - Automatic UTC storage with local timezone display
  - Smart conversion between UTC and local time
  - Timezone-aware datetime inputs and displays
  - Proper timezone handling in CSV exports
- Added timezone selection in system settings
- Enhanced datetime display consistency across all views

### Improvements
- Refactored datetime handling for better consistency
- Updated CSV exports to respect timezone settings
- Enhanced activity history display with proper timezone conversion
- Improved follow-up scheduling with timezone awareness
- Updated dashboard datetime displays

### Bug Fixes
- Fixed inconsistent datetime display in activity lists
- Corrected timezone handling in CSV exports
- Resolved date formatting issues in follow-up views
- Fixed datetime conversion in history form
- Addressed timezone-related display issues in the dashboard

### Technical Details
- Added new timezone helper functions:
  - getSystemTimezone()
  - utcToLocal()
  - localToUtc()
- Enhanced database storage to consistently use UTC
- Improved datetime input handling in forms
- Added timezone configuration in system settings

## Known Issues
- Very large data exports (>10,000 records) may time out on some server configurations
- Internet Explorer 11 has limited support for some UI features
- Some visual elements may not render correctly on very small screens (<320px)

## Upgrade Instructions

For users upgrading from v1.5.1:

1. Back up your database and files
2. Download the latest release
3. Replace all files except `includes/config.php`
4. Run any pending database migrations
5. Configure your timezone in System Settings

Note: Existing datetime data will be interpreted as UTC. If your existing data was stored in a different timezone, please contact support for migration assistance.

## Credits

Rey CRM System is developed and maintained by VIBE Coding.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

---

Thank you for using Rey CRM System! We welcome your feedback and contributions.
