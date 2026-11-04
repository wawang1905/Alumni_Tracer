import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
import matplotlib.ticker as mticker
from datetime import datetime, timedelta
import random
 
# ── 1. Generate Sample Data ──────────────────────────────────────────────────
random.seed(42)
np.random.seed(42)
 
products   = ["Laptop", "Phone", "Tablet", "Headphones", "Smartwatch"]
regions    = ["North", "South", "East", "West"]
start_date = datetime(2024, 1, 1)
 
rows = []
for i in range(200):
    product  = random.choice(products)
    region   = random.choice(regions)
    date     = start_date + timedelta(days=random.randint(0, 364))
    qty      = random.randint(1, 20)
    price    = {"Laptop": 1200, "Phone": 800, "Tablet": 500,
                "Headphones": 150, "Smartwatch": 300}[product]
    revenue  = qty * price * round(random.uniform(0.85, 1.15), 2)
    rows.append([date, product, region, qty, round(revenue, 2)])
 
df = pd.DataFrame(rows, columns=["Date", "Product", "Region", "Quantity", "Revenue"])
df["Month"] = df["Date"].dt.to_period("M").astype(str)
 
print("=" * 55)
print("  SALES ANALYTICS REPORT")
print("=" * 55)
 
# ── 2. Basic Summary ─────────────────────────────────────────────────────────
print("\n📊 Dataset Overview")
print(f"   Records  : {len(df)}")
print(f"   Date Range: {df['Date'].min().date()} → {df['Date'].max().date()}")
print(f"   Products : {df['Product'].nunique()}")
print(f"   Regions  : {df['Region'].nunique()}")
 
print("\n📋 First 5 Rows")
print(df.head().to_string(index=False))
 
print("\n📈 Descriptive Statistics")
print(df[["Quantity", "Revenue"]].describe().round(2))
 
# ── 3. Aggregations ──────────────────────────────────────────────────────────
print("\n💰 Revenue by Product (sorted)")
rev_product = (df.groupby("Product")["Revenue"]
                 .sum()
                 .sort_values(ascending=False)
                 .reset_index())
rev_product.columns = ["Product", "Total Revenue"]
rev_product["Total Revenue"] = rev_product["Total Revenue"].map("${:,.0f}".format)
print(rev_product.to_string(index=False))
 
print("\n🌍 Revenue by Region")
rev_region = (df.groupby("Region")["Revenue"]
                .agg(["sum", "mean", "count"])
                .rename(columns={"sum": "Total", "mean": "Avg per Sale", "count": "Sales"})
                .sort_values("Total", ascending=False))
print(rev_region.round(2))
 
print("\n📅 Monthly Revenue Trend (Jan–Dec 2024)")
monthly = df.groupby("Month")["Revenue"].sum().reset_index()
for _, row in monthly.iterrows():
    bar = "█" * int(row["Revenue"] / 3000)
    print(f"   {row['Month']}  {bar}  ${row['Revenue']:,.0f}")
 
# ── 4. Advanced Analytics ────────────────────────────────────────────────────
print("\n🔝 Top 5 Best-Selling Days")
top_days = (df.groupby("Date")["Revenue"]
              .sum()
              .sort_values(ascending=False)
              .head(5)
              .reset_index())
top_days["Revenue"] = top_days["Revenue"].map("${:,.0f}".format)
print(top_days.to_string(index=False))
 
print("\n📦 Quantity Sold by Product")
qty_product = df.groupby("Product")["Quantity"].sum().sort_values(ascending=False)
print(qty_product)
 
print("\n📊 Revenue Correlation Matrix")
pivot = df.pivot_table(values="Revenue", index="Region", columns="Product", aggfunc="sum").fillna(0)
print(pivot.round(0).astype(int))
 
# ── 5. Visualizations ────────────────────────────────────────────────────────
fig, axes = plt.subplots(2, 2, figsize=(14, 10))
fig.suptitle("Sales Analytics Dashboard — 2024", fontsize=16, fontweight="bold", y=1.01)
 
colors = ["#4F8EF7", "#F76C6C", "#6CF7A8", "#F7C46C", "#C46CF7"]
 
# Plot 1 – Revenue by Product (bar)
ax1 = axes[0, 0]
rp = df.groupby("Product")["Revenue"].sum().sort_values()
rp.plot(kind="barh", ax=ax1, color=colors)
ax1.set_title("Total Revenue by Product")
ax1.set_xlabel("Revenue (USD)")
ax1.xaxis.set_major_formatter(mticker.FuncFormatter(lambda x, _: f"${x:,.0f}"))
ax1.grid(axis="x", linestyle="--", alpha=0.4)
 
# Plot 2 – Monthly Revenue Trend (line)
ax2 = axes[0, 1]
monthly_sorted = df.groupby("Month")["Revenue"].sum()
monthly_sorted.plot(kind="line", ax=ax2, marker="o", color="#4F8EF7", linewidth=2)
ax2.set_title("Monthly Revenue Trend")
ax2.set_ylabel("Revenue (USD)")
ax2.yaxis.set_major_formatter(mticker.FuncFormatter(lambda x, _: f"${x:,.0f}"))
ax2.tick_params(axis="x", rotation=45)
ax2.grid(linestyle="--", alpha=0.4)
 
# Plot 3 – Revenue by Region (pie)
ax3 = axes[1, 0]
rr = df.groupby("Region")["Revenue"].sum()
ax3.pie(rr, labels=rr.index, autopct="%1.1f%%", colors=colors[:4], startangle=140)
ax3.set_title("Revenue Share by Region")
 
# Plot 4 – Quantity Sold by Product (bar)
ax4 = axes[1, 1]
qp = df.groupby("Product")["Quantity"].sum().sort_values(ascending=False)
qp.plot(kind="bar", ax=ax4, color=colors)
ax4.set_title("Total Units Sold by Product")
ax4.set_ylabel("Units Sold")
ax4.set_xlabel("")
ax4.tick_params(axis="x", rotation=25)
ax4.grid(axis="y", linestyle="--", alpha=0.4)
 
plt.tight_layout()
plt.savefig("sales_dashboard.png", dpi=150, bbox_inches="tight")
print("\n✅ Dashboard saved → sales_dashboard.png")
 
# ── 6. Insight Summary ───────────────────────────────────────────────────────
total_rev   = df["Revenue"].sum()
best_prod   = df.groupby("Product")["Revenue"].sum().idxmax()
best_region = df.groupby("Region")["Revenue"].sum().idxmax()
best_month  = df.groupby("Month")["Revenue"].sum().idxmax()
 
print("\n" + "=" * 55)
print("  KEY INSIGHTS")
print("=" * 55)
print(f"   Total Revenue  : ${total_rev:,.0f}")
print(f"   Top Product    : {best_prod}")
print(f"   Top Region     : {best_region}")
print(f"   Best Month     : {best_month}")
print("=" * 55)
