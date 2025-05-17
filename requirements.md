# Vibe Coding
This is the first requirements made for deepseek to create in Chat first then copy over.  After that I use Github Copilot, Claude AI Sonnet to polish it.
## My Personal CRM
### Consisted of 4 forms
1. From1: List of All customers: user can click create new Customer
    - Table colums shows Company Name, Location, Last Contact, Status
    - Select a customer in the list then user can click "Edit", "View" Then go to Form2.  
    - Select a customer in the list then user can delete customer, On Delete, popup to confirm deletion, on confirm, delete the record and make sure delete the related customers, and related history.
    - Dashboard Button on top right corner, on click go to dashboard
2. Form2: Create, Edit, View Customer, with this same form user can Create, edit, View customer detail (on view disable all text box)
    - customer detail includes:
        1. Company Name (Required:Text)
        2. Location (Required:Text)
        3. Company Type (Not Required:Text)
        4. Contact Phone (Not Required:phone)
        5. Contact Email (Not Required: Email)
        6. Status Field (Not Required: Active, Inactive, Prospect)
        7. Note (Not Required: Text)
        8. Contact Person Information Table List
            - Can Add, Edit, Delete Contact Person on View Customer Mode, Edit Customer Mode
            - Contact Person Table list shows columns: Name, Contact Number, Contact Email
            - Select a contact Person Information then can click "Edit", Delete" button
                - On click Add go to form3 with Add parameter
                - On Click Edit go to form3 with Edit Parameter
                - On Click View go to form3 with View Parameter
                - On Click Delete, popup confirm, on confirm delete the record. make sure after delelte to handle refresh to the same page with right action, and id.
        9. Show a table list of Action history (last update on top)
            - Can Add, Edit, Delete Action History on View Customer Mode, Edit Customer Mode 
            - table shows Action, Response, Next Step
            - Select an action history in the action history list then can click "Edit", "Delete", "View" button
                - On click Add go to form4 with Add parameter
                - On Click Edit go to form4 with Edit Parameter
                - On Click View go to form4 with View Parameter
                - On Click Delete, popup confirm, on confirm delete the record. make sure after delelte to handle refresh to the same page with right action, and id.
    - View Mode Shows
        1 Back Button on the to right corner, on click go back to form1
        2. Add, Edit, Delete Contact Persons and Action history
    - Add Mode shows Add Customer, Cancel Buttons
    - Edit Mode Shows Save customer, cancel buttons
3. Form3: Create, Edit Contact Person
    - View Mode shows only Back button
    - Add Mode show Add Contact button, Cancel Button
    - Edit Mode shows Save Contact Button, Cancel buttons.
    - Contact Person information includes
        1. Name (Required:Text)
        2. Title (Not Required:Text)
        3. Role (Not Required:Text)
        4. Contact Number (Not Required:Phone)
        5. Contact Email (Not Required:Email)
        6. Note(Not Required:Text)
    - Click cancle to goback to Form2
    - On Click OK
        - if the user get to this form with Add Button, then Create new Record, then go back to Form2
        - if the user get to this form with Edit Button, then update Record, then go back to From2
4. Form4: Create, Edit History 
    - View Mode shows only Back button
    - Add Mode show Add Action Button, Cancel Button
    - Edit Mode shows Save Action Button, Cancel buttons.
    - Action History Fields includes:
        0. Datetime (Required: Autofilled but editable)
        1. Action (Required:Text)
        2. Response (Required:Text)
        3. Next Step (Required:Text)
        4. Follow up date Time (Required:Date Time)
        5. Contact Person (Required,List Contact Persons in this company to choose)
        6. Note (Not Required: Text)
    - Click cancel to goback to Form2
    - On Click OK
        - if the user get to this form with Add Button, then Create new Record, then go back to Form2
        - if the user get to this form with Edit Button, then update Record, then go back to Form2

### A Dashboard
1. Dashboard should show 
    - How many total customers
    - Show percentaage of each customer locations
    - Last Contacted customer
    - percentage contacted, no contacted
    - back button, onclick go back to form1
    - Upcoming Follow up Sections (Could be table of upcoming 5 rows)
    - "recent activities" timeline
    - Export log to csv (Action, Time)
        - Add Customer
        - Contact Customer
        - Follow up Customer
        - Next Step Action
