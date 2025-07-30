# Database.sql Update Summary

## Overview
Updated the main `database.sql` file to include the new customer status system that was previously only available through migration scripts.

## Changes Made

### 1. Removed Old Status System
- **Removed**: Old ENUM-based status column from customers table
- **Old Definition**: 
  ```sql
  status ENUM('Prospect', 'Qualified', 'Not Qualified', 'New Customer', 'Active Customer', 'Inactive Customer', 'Lost Customer', 'Closed Lost', 'Closed Won') DEFAULT 'Prospect'
  ```

### 2. Added New Status System Tables

#### customer_statuses Table
- **Purpose**: Define available customer statuses
- **Key Fields**: `id`, `status_key`, `sort_order`, `is_active`
- **Status Keys**: `prospect`, `qualified`, `not_qualified`, `new_customer`, `active_customer`, `lost_customer`

#### customer_status_translations Table  
- **Purpose**: Provide internationalized status names
- **Key Fields**: `id`, `status_id`, `locale`, `name`, `description`
- **Supported Locales**: English (`en`), Chinese (`zh-cn`)
- **Unique Constraint**: Each status can only have one translation per locale

#### customer_status_history Table
- **Purpose**: Track status change timeline
- **Key Fields**: `id`, `customer_id`, `from_status_id`, `to_status_id`, `changed_by`, `changed_at`, `notes`
- **Foreign Keys**: References customers, statuses, and users tables
- **Indexes**: Optimized for timeline queries

### 3. Updated Customers Table
- **Added**: `status_id INT NOT NULL DEFAULT 1` (references customer_statuses.id)
- **Added**: `status_changed_at TIMESTAMP NULL` (tracks when status was last changed)
- **Added**: `status_changed_by INT NULL` (tracks who changed the status)
- **Removed**: Old `status` ENUM column
- **Foreign Keys**: Added constraints for status relationships
- **Indexes**: Added for better query performance

### 4. Pre-populated Data
- **Status Definitions**: All 6 statuses inserted with proper sort order
- **English Translations**: Complete translations for all statuses
- **Chinese Translations**: Complete translations for all statuses (潜在客户, 洽谈客户, etc.)

## Status Flow Implementation
The system now supports the required status flow from requirements:
```
潜在客户 (Prospect) → 洽谈客户 (Qualified) → 成交客户 (New Customer) → 回头客户 (Active Customer)
      ↓                    ↓         
   无效客户 (Not Qualified)  失去客户 (Lost Customer)
```

## Database Compatibility
- ✅ **Backward Compatible**: New installs will work correctly
- ✅ **Migration Safe**: Existing databases can use migration scripts
- ✅ **Foreign Key Integrity**: All relationships properly defined
- ✅ **Index Optimization**: Performance indexes added
- ✅ **Syntax Validation**: Successfully tested with MySQL

## Key Benefits
1. **Internationalization**: Status names in multiple languages
2. **Timeline Tracking**: Complete history of status changes
3. **Data Integrity**: Foreign key constraints prevent invalid data
4. **Performance**: Proper indexing for fast queries
5. **Extensibility**: Easy to add new statuses and translations

## Usage Notes
- Default status for new customers is `prospect` (id: 1)
- Status changes are automatically tracked in history table
- All status-related functions now use the new table structure
- Translations are loaded based on user's preferred language

The updated `database.sql` file is now the single source of truth for the complete database schema including the customer status system.
