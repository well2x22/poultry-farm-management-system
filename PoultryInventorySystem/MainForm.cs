using System;
using System.Collections.Generic;
using System.Drawing;
using System.Linq;
using System.Net;
using System.Threading.Tasks;
using System.Web.Script.Serialization;
using System.Windows.Forms;

namespace PoultryInventorySystem
{
    public class MainForm : Form
    {
        // Change this URL only if your API file/location is different.
        private const string ApiUrl = "http://localhost/poultry-farm-management-system/egg-trading-system/api/egg_inventory.php";

        private Label lblStatus;
        private Label lblTotalEggs;
        private Label lblExtraLarge;
        private Label lblLarge;
        private Label lblMedium;
        private Label lblSmall;
        private Button btnRefresh;
        private DataGridView dgvInventory;
        private TextBox txtSearch;

        private List<InventoryItem> currentItems = new List<InventoryItem>();

        public MainForm()
        {
            BuildInterface();
        }

        protected override async void OnLoad(EventArgs e)
        {
            base.OnLoad(e);
            await LoadInventoryDataAsync();
        }

        private void BuildInterface()
        {
            Text = "Poultry Inventory System";
            StartPosition = FormStartPosition.CenterScreen;
            Size = new Size(1160, 700);
            MinimumSize = new Size(1000, 620);
            BackColor = Color.FromArgb(245, 246, 248);
            Font = new Font("Segoe UI", 9F, FontStyle.Regular);

            Panel header = new Panel
            {
                Dock = DockStyle.Top,
                Height = 60,
                BackColor = Color.FromArgb(33, 37, 41)
            };
            Controls.Add(header);

            Label title = new Label
            {
                Text = "Poultry Inventory System",
                ForeColor = Color.White,
                Font = new Font("Segoe UI", 15F, FontStyle.Bold),
                AutoSize = true,
                Location = new Point(22, 17)
            };
            header.Controls.Add(title);

            btnRefresh = new Button
            {
                Text = "Refresh Inventory",
                Width = 155,
                Height = 34,
                Location = new Point(970, 13),
                BackColor = Color.FromArgb(255, 193, 7),
                ForeColor = Color.Black,
                FlatStyle = FlatStyle.Flat
            };
            btnRefresh.FlatAppearance.BorderSize = 0;
            btnRefresh.Click += async (s, e) => await LoadInventoryDataAsync();
            header.Controls.Add(btnRefresh);

            Label dashboardTitle = new Label
            {
                Text = "Inventory Dashboard",
                Font = new Font("Segoe UI", 17F, FontStyle.Bold),
                AutoSize = true,
                Location = new Point(28, 82)
            };
            Controls.Add(dashboardTitle);

            lblStatus = new Label
            {
                Text = "This desktop inventory system loads records through the API layer.",
                Font = new Font("Segoe UI", 9F),
                ForeColor = Color.DimGray,
                AutoSize = true,
                Location = new Point(31, 116)
            };
            Controls.Add(lblStatus);

            lblTotalEggs = CreateSummaryCard("Total Eggs in Inventory", "0", 30, 150, 1080, 88, true);
            lblExtraLarge = CreateSummaryCard("Extra Large Eggs", "0", 30, 260, 255, 88, false);
            lblLarge = CreateSummaryCard("Large Eggs", "0", 305, 260, 255, 88, false);
            lblMedium = CreateSummaryCard("Medium Eggs", "0", 580, 260, 255, 88, false);
            lblSmall = CreateSummaryCard("Small Eggs", "0", 855, 260, 255, 88, false);

            Label recordsTitle = new Label
            {
                Text = "Egg Inventory Records",
                Font = new Font("Segoe UI", 12F, FontStyle.Bold),
                AutoSize = true,
                Location = new Point(30, 375)
            };
            Controls.Add(recordsTitle);

            Label searchLabel = new Label
            {
                Text = "Search:",
                AutoSize = true,
                Location = new Point(770, 378)
            };
            Controls.Add(searchLabel);

            txtSearch = new TextBox
            {
                Width = 260,
                Location = new Point(825, 373)
            };
            txtSearch.TextChanged += (s, e) => ApplySearchFilter();
            Controls.Add(txtSearch);

            dgvInventory = new DataGridView
            {
                Location = new Point(30, 410),
                Size = new Size(1080, 220),
                BackgroundColor = Color.White,
                BorderStyle = BorderStyle.FixedSingle,
                AutoSizeColumnsMode = DataGridViewAutoSizeColumnsMode.Fill,
                SelectionMode = DataGridViewSelectionMode.FullRowSelect,
                MultiSelect = false,
                ReadOnly = true,
                AllowUserToAddRows = false,
                AllowUserToDeleteRows = false,
                RowHeadersVisible = false
            };
            Controls.Add(dgvInventory);
        }

        private Label CreateSummaryCard(string title, string value, int x, int y, int width, int height, bool isTotal)
        {
            Panel card = new Panel
            {
                Location = new Point(x, y),
                Size = new Size(width, height),
                BackColor = isTotal ? Color.FromArgb(255, 193, 7) : Color.White,
                BorderStyle = BorderStyle.FixedSingle
            };
            Controls.Add(card);

            Label titleLabel = new Label
            {
                Text = title,
                Font = new Font("Segoe UI", 10F, FontStyle.Bold),
                AutoSize = true,
                Location = new Point(16, 12)
            };
            card.Controls.Add(titleLabel);

            Label valueLabel = new Label
            {
                Text = value,
                Font = new Font("Segoe UI", isTotal ? 25F : 21F, FontStyle.Bold),
                AutoSize = true,
                Location = new Point(16, 38)
            };
            card.Controls.Add(valueLabel);

            return valueLabel;
        }

        private async Task LoadInventoryDataAsync()
        {
            try
            {
                btnRefresh.Enabled = false;
                lblStatus.Text = "Loading inventory data from API...";

                string json;
                using (WebClient client = new WebClient())
                {
                    client.Headers.Add("Accept", "application/json");
                    json = await client.DownloadStringTaskAsync(ApiUrl);
                }

                JavaScriptSerializer serializer = new JavaScriptSerializer();
                ApiResponse response = serializer.Deserialize<ApiResponse>(json);

                if (response == null || response.data == null)
                {
                    MessageBox.Show("Invalid API response format.", "API Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
                    lblStatus.Text = "Invalid API response.";
                    return;
                }

                currentItems = response.data;
                DisplaySummary(currentItems);
                BindTable(currentItems);

                lblStatus.Text = "Inventory data loaded successfully from API.";
            }
            catch (WebException ex)
            {
                MessageBox.Show(
                    "Cannot connect to the API. Make sure XAMPP Apache is running and the PHP API file exists.\n\n" + ex.Message,
                    "Connection Error",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Error
                );
                lblStatus.Text = "Connection failed. API may be offline.";
            }
            catch (Exception ex)
            {
                MessageBox.Show("Unexpected error: " + ex.Message, "Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
                lblStatus.Text = "Unexpected error occurred.";
            }
            finally
            {
                btnRefresh.Enabled = true;
            }
        }

        private void DisplaySummary(List<InventoryItem> items)
        {
            lblTotalEggs.Text = items.Sum(i => i.quantity).ToString();
            lblExtraLarge.Text = items.Where(i => i.egg_size == "Extra Large").Sum(i => i.quantity).ToString();
            lblLarge.Text = items.Where(i => i.egg_size == "Large").Sum(i => i.quantity).ToString();
            lblMedium.Text = items.Where(i => i.egg_size == "Medium").Sum(i => i.quantity).ToString();
            lblSmall.Text = items.Where(i => i.egg_size == "Small").Sum(i => i.quantity).ToString();
        }

        private void ApplySearchFilter()
        {
            string term = txtSearch.Text.Trim().ToLower();

            if (string.IsNullOrEmpty(term))
            {
                BindTable(currentItems);
                return;
            }

            var filtered = currentItems
                .Where(i =>
                    (i.batch_code ?? "").ToLower().Contains(term) ||
                    (i.egg_size ?? "").ToLower().Contains(term) ||
                    (i.received_date ?? "").ToLower().Contains(term))
                .ToList();

            BindTable(filtered);
        }

        private void BindTable(List<InventoryItem> items)
        {
            dgvInventory.DataSource = items.Select(i => new
            {
                ID = i.id,
                BatchCode = i.batch_code,
                EggSize = i.egg_size,
                Quantity = i.quantity,
                ReceivedDate = i.received_date,
                DateCreated = i.created_at
            }).ToList();

            if (dgvInventory.Columns.Count > 0)
            {
                dgvInventory.Columns["ID"].HeaderText = "ID";
                dgvInventory.Columns["BatchCode"].HeaderText = "Batch Code";
                dgvInventory.Columns["EggSize"].HeaderText = "Egg Size";
                dgvInventory.Columns["Quantity"].HeaderText = "Quantity";
                dgvInventory.Columns["ReceivedDate"].HeaderText = "Received Date";
                dgvInventory.Columns["DateCreated"].HeaderText = "Date Created";
            }
        }
    }
}
