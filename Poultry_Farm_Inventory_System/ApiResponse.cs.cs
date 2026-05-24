using System.Collections.Generic;

namespace Poultry_Farm_Inventory_System
{
    public class ApiResponse
    {
        public bool status { get; set; }
        public string message { get; set; }
        public List<EggInventory> data { get; set; }
    }
}