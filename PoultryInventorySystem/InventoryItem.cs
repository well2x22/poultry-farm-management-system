namespace PoultryInventorySystem
{
    public class InventoryItem
    {
        public int id { get; set; }
        public string batch_code { get; set; }
        public string egg_size { get; set; }
        public int quantity { get; set; }
        public string received_date { get; set; }
        public string created_at { get; set; }
        public string updated_at { get; set; }
    }
}
