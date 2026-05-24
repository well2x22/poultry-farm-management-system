using Newtonsoft.Json;
using System;
using System.Collections.Generic;
using System.Drawing;
using System.Linq;
using System.Net.Http;
using System.Threading.Tasks;
using System.Windows.Forms;

namespace Poultry_Farm_Inventory_System
{
    public partial class MainForm : Form
    {
        private readonly string apiUrl = "http://localhost/poultry-farm-management-system/egg-trading-system/api/egg_inventory.php";

        private Label lblTotalEggs;
        private Label lblExtraLarge;
        private Label lblLarge;
        private Label lblMedium;
        private Label lblSmall;
        private Label lblStatus;

        private TextBox txtSearch;
        private Button btnRefresh;
        private DataGridView dgvInventory;

        private List<EggInventory> inventoryList = new List<EggInventory>();

        public MainForm()
        {
            BuildInterface();
        }

        protected override async void OnLoad(EventArgs e)
        {
            base.OnLoad(e);
            await LoadInventoryData();
        }

        private void BuildInterface()
        {
            this.Text = "Poultry Inventory API System";
            this.Size = new Size(1180, 720);
            this.StartPosition = FormStartPosition.CenterScreen;
            this.BackColor = Color.FromArgb(248, 249, 250);
            this.Font = new Font("Segoe UI", 9);

            // =========================
            // TOP NAVBAR
            // =========================
            Panel navbar = new Panel();
            navbar.Dock = DockStyle.Top;
            navbar.Height = 50;
            navbar.BackColor = Color.FromArgb(33, 37, 41);
            this.Controls.Add(navbar);

            Label navTitle = new Label();
            navTitle.Text = "Poultry Inventory API System";
            navTitle.ForeColor = Color.White;
            navTitle.Font = new Font("Segoe UI", 12, FontStyle.Bold);
            navTitle.AutoSize = true;
            navTitle.Location = new Point(15, 15);
            navbar.Controls.Add(navTitle);

            btnRefresh = new Button();
            btnRefresh.Text = "Refresh Inventory";
            btnRefresh.Width = 145;
            btnRefresh.Height = 30;
            btnRefresh.Location = new Point(1000, 10);
            btnRefresh.BackColor = Color.FromArgb(255, 193, 7);
            btnRefresh.ForeColor = Color.Black;
            btnRefresh.FlatStyle = FlatStyle.Flat;
            btnRefresh.FlatAppearance.BorderSize = 0;
            btnRefresh.Font = new Font("Segoe UI", 8, FontStyle.Bold);
            btnRefresh.Click += async (s, e) => await LoadInventoryData();
            navbar.Controls.Add(btnRefresh);

            // =========================
            // MAIN CONTAINER
            // =========================
            Panel container = new Panel();
            container.Location = new Point(210, 75);
            container.Size = new Size(760, 590);
            container.BackColor = Color.Transparent;
            this.Controls.Add(container);

            Label title = new Label();
            title.Text = "Inventory Dashboard";
            title.Font = new Font("Segoe UI", 18, FontStyle.Bold);
            title.ForeColor = Color.FromArgb(20, 20, 20);
            title.AutoSize = true;
            title.Location = new Point(0, 0);
            container.Controls.Add(title);

            lblStatus = new Label();
            lblStatus.Text = "This page displays egg inventory records received from the Poultry Egg System.";
            lblStatus.Font = new Font("Segoe UI", 9);
            lblStatus.ForeColor = Color.FromArgb(90, 90, 90);
            lblStatus.AutoSize = true;
            lblStatus.Location = new Point(2, 38);
            container.Controls.Add(lblStatus);

            // =========================
            // TOTAL EGGS CARD
            // =========================
            lblTotalEggs = CreateCard(
                container,
                "Total Eggs in Inventory",
                "0",
                0,
                75,
                760,
                90,
                true
            );

            // =========================
            // SIZE SUMMARY CARDS
            // =========================
            lblExtraLarge = CreateCard(container, "Extra Large Eggs", "0", 0, 185, 175, 80, false);
            lblLarge = CreateCard(container, "Large Eggs", "0", 195, 185, 175, 80, false);
            lblMedium = CreateCard(container, "Medium Eggs", "0", 390, 185, 175, 80, false);
            lblSmall = CreateCard(container, "Small Eggs", "0", 585, 185, 175, 80, false);

            // =========================
            // TABLE CARD
            // =========================
            Panel tableCard = new Panel();
            tableCard.Location = new Point(0, 285);
            tableCard.Size = new Size(760, 285);
            tableCard.BackColor = Color.White;
            tableCard.BorderStyle = BorderStyle.FixedSingle;
            container.Controls.Add(tableCard);

            Panel tableHeader = new Panel();
            tableHeader.Dock = DockStyle.Top;
            tableHeader.Height = 38;
            tableHeader.BackColor = Color.FromArgb(33, 37, 41);
            tableCard.Controls.Add(tableHeader);

            Label tableTitle = new Label();
            tableTitle.Text = "Egg Inventory Records";
            tableTitle.ForeColor = Color.White;
            tableTitle.Font = new Font("Segoe UI", 9, FontStyle.Bold);
            tableTitle.AutoSize = true;
            tableTitle.Location = new Point(12, 10);
            tableHeader.Controls.Add(tableTitle);

            Label searchLabel = new Label();
            searchLabel.Text = "Search:";
            searchLabel.ForeColor = Color.White;
            searchLabel.Font = new Font("Segoe UI", 8, FontStyle.Bold);
            searchLabel.AutoSize = true;
            searchLabel.Location = new Point(475, 11);
            tableHeader.Controls.Add(searchLabel);

            txtSearch = new TextBox();
            txtSearch.Width = 190;
            txtSearch.Height = 22;
            txtSearch.Location = new Point(535, 8);
            txtSearch.Font = new Font("Segoe UI", 8);
            txtSearch.TextChanged += (s, e) => DisplayTable();
            tableHeader.Controls.Add(txtSearch);

            dgvInventory = new DataGridView();
            dgvInventory.Location = new Point(12, 52);
            dgvInventory.Size = new Size(735, 215);
            dgvInventory.BackgroundColor = Color.White;
            dgvInventory.BorderStyle = BorderStyle.None;
            dgvInventory.AutoSizeColumnsMode = DataGridViewAutoSizeColumnsMode.Fill;
            dgvInventory.SelectionMode = DataGridViewSelectionMode.FullRowSelect;
            dgvInventory.MultiSelect = false;
            dgvInventory.ReadOnly = true;
            dgvInventory.AllowUserToAddRows = false;
            dgvInventory.AllowUserToDeleteRows = false;
            dgvInventory.RowHeadersVisible = false;

            dgvInventory.EnableHeadersVisualStyles = false;
            dgvInventory.ColumnHeadersDefaultCellStyle.BackColor = Color.FromArgb(33, 37, 41);
            dgvInventory.ColumnHeadersDefaultCellStyle.ForeColor = Color.White;
            dgvInventory.ColumnHeadersDefaultCellStyle.Font = new Font("Segoe UI", 8, FontStyle.Bold);
            dgvInventory.ColumnHeadersHeight = 32;

            dgvInventory.DefaultCellStyle.Font = new Font("Segoe UI", 8);
            dgvInventory.DefaultCellStyle.SelectionBackColor = Color.FromArgb(255, 236, 179);
            dgvInventory.DefaultCellStyle.SelectionForeColor = Color.Black;
            dgvInventory.AlternatingRowsDefaultCellStyle.BackColor = Color.FromArgb(240, 240, 240);

            tableCard.Controls.Add(dgvInventory);
        }

        private Label CreateCard(
            Control parent,
            string title,
            string value,
            int x,
            int y,
            int width,
            int height,
            bool isTotal
        )
        {
            Panel card = new Panel();
            card.Location = new Point(x, y);
            card.Size = new Size(width, height);
            card.BackColor = isTotal ? Color.FromArgb(255, 193, 7) : Color.White;
            card.BorderStyle = BorderStyle.FixedSingle;
            parent.Controls.Add(card);

            Label titleLabel = new Label();
            titleLabel.Text = title;
            titleLabel.Font = new Font("Segoe UI", 9, FontStyle.Bold);
            titleLabel.ForeColor = Color.Black;
            titleLabel.AutoSize = true;
            titleLabel.Location = new Point(15, 13);
            card.Controls.Add(titleLabel);

            Label valueLabel = new Label();
            valueLabel.Text = value;
            valueLabel.Font = new Font("Segoe UI", isTotal ? 24 : 18, FontStyle.Bold);
            valueLabel.ForeColor = Color.Black;
            valueLabel.AutoSize = true;
            valueLabel.Location = new Point(15, isTotal ? 42 : 38);
            card.Controls.Add(valueLabel);

            return valueLabel;
        }

        private async Task LoadInventoryData()
        {
            try
            {
                lblStatus.Text = "Loading inventory data from API...";
                btnRefresh.Enabled = false;

                using (HttpClient client = new HttpClient())
                {
                    client.Timeout = TimeSpan.FromSeconds(10);

                    HttpResponseMessage response = await client.GetAsync(apiUrl);

                    if (!response.IsSuccessStatusCode)
                    {
                        MessageBox.Show(
                            "API request failed: " + response.StatusCode,
                            "API Error",
                            MessageBoxButtons.OK,
                            MessageBoxIcon.Error
                        );

                        lblStatus.Text = "API request failed.";
                        return;
                    }

                    string json = await response.Content.ReadAsStringAsync();

                    ApiResponse apiResponse = JsonConvert.DeserializeObject<ApiResponse>(json);

                    if (apiResponse == null || apiResponse.data == null)
                    {
                        MessageBox.Show(
                            "Invalid API response.",
                            "API Error",
                            MessageBoxButtons.OK,
                            MessageBoxIcon.Error
                        );

                        lblStatus.Text = "Invalid API response.";
                        return;
                    }

                    inventoryList = apiResponse.data;

                    DisplaySummary();
                    DisplayTable();

                    lblStatus.Text = "Inventory data loaded successfully from API.";
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show(
                    "Cannot load inventory from API.\n\n" + ex.Message,
                    "Connection Error",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Error
                );

                lblStatus.Text = "Connection failed. Check XAMPP Apache and API URL.";
            }
            finally
            {
                btnRefresh.Enabled = true;
            }
        }

        private void DisplaySummary()
        {
            lblTotalEggs.Text = inventoryList.Sum(i => i.quantity).ToString();

            lblExtraLarge.Text = inventoryList
                .Where(i => i.egg_size == "Extra Large")
                .Sum(i => i.quantity)
                .ToString();

            lblLarge.Text = inventoryList
                .Where(i => i.egg_size == "Large")
                .Sum(i => i.quantity)
                .ToString();

            lblMedium.Text = inventoryList
                .Where(i => i.egg_size == "Medium")
                .Sum(i => i.quantity)
                .ToString();

            lblSmall.Text = inventoryList
                .Where(i => i.egg_size == "Small")
                .Sum(i => i.quantity)
                .ToString();
        }

        private void DisplayTable()
        {
            string keyword = txtSearch.Text.Trim().ToLower();

            var filtered = inventoryList
                .Where(i =>
                    string.IsNullOrEmpty(keyword) ||
                    (i.batch_code != null && i.batch_code.ToLower().Contains(keyword)) ||
                    (i.egg_size != null && i.egg_size.ToLower().Contains(keyword)) ||
                    (i.received_date != null && i.received_date.ToLower().Contains(keyword))
                )
                .Select(i => new
                {
                    BatchCode = i.batch_code,
                    EggSize = i.egg_size,
                    Quantity = i.quantity,
                    ReceivedDate = i.received_date,
                    DateCreated = i.created_at
                })
                .ToList();

            dgvInventory.DataSource = filtered;

            if (dgvInventory.Columns.Count > 0)
            {
                dgvInventory.Columns["BatchCode"].HeaderText = "Batch Code";
                dgvInventory.Columns["EggSize"].HeaderText = "Egg Size";
                dgvInventory.Columns["ReceivedDate"].HeaderText = "Received Date";
                dgvInventory.Columns["DateCreated"].HeaderText = "Date Created";
            }
        }

        private void InitializeComponent()
        {
            this.SuspendLayout();
            // 
            // MainForm
            // 
            this.ClientSize = new System.Drawing.Size(284, 261);
            this.Name = "MainForm";
            this.Load += new System.EventHandler(this.MainForm_Load);
            this.ResumeLayout(false);

        }

        private void MainForm_Load(object sender, EventArgs e)
        {

        }
    }
}