PoultryInventorySystem - C# Windows Forms

How to use:
1. Copy this folder to:
   C:\xampp\htdocs\poultry-farm-management-system\inventory-windows-form

2. Make sure your PHP API endpoint exists:
   http://localhost/poultry-farm-management-system/egg-trading-system/api/egg_inventory.php

3. Open PoultryInventorySystem.sln in Visual Studio.

4. Build and Run.

Notes:
- This Windows Form does NOT connect directly to MySQL.
- It retrieves inventory records through the API layer.
- It uses built-in JavaScriptSerializer, so Newtonsoft.Json is not required.
