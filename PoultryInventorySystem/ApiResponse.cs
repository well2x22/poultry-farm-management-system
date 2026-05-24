using System.Collections.Generic;

namespace PoultryInventorySystem
{
    public class ApiResponse
    {
        public bool status { get; set; }
        public string message { get; set; }
        public List<InventoryItem> data { get; set; }
    }
}
