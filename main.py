import tkinter as tk
from tkinter import ttk, messagebox
import woocommerce

class WooCommerceApp:
    def __init__(self, root):
        self.root = root
        self.root.title("مدیریت محصولات ووکامرس")

        # اتصال به ووکامرس
        self.wcapi = woocommerce.API(
            url="https://tahrirchishop.com/",
            consumer_key="ck_4580190401849ee1e7fed019426235cc1c423284",
            consumer_secret="cs_fe19093d4cd1bb4c5c848584981f685a5ccc48d4",
            version="wc/v3"
        )

        # ایجاد رابط کاربری
        self.create_widgets()

    def create_widgets(self):
        # فریم اصلی
        main_frame = ttk.Frame(self.root, padding="10")
        main_frame.grid(row=0, column=0, sticky=(tk.W, tk.E, tk.N, tk.S))

        # لیست محصولات
        self.products_tree = ttk.Treeview(main_frame, columns=("id", "name", "price", "stock", "status"), show="headings")
        self.products_tree.heading("id", text="شناسه")
        self.products_tree.heading("name", text="نام محصول")
        self.products_tree.heading("price", text="قیمت")
        self.products_tree.heading("stock", text="موجودی")
        self.products_tree.heading("status", text="وضعیت")
        self.products_tree.grid(row=0, column=0, columnspan=4, sticky=(tk.W, tk.E, tk.N, tk.S))

        # دکمه‌ها
        button_frame = ttk.Frame(main_frame)
        button_frame.grid(row=1, column=0, columnspan=4, pady=10)

        refresh_button = ttk.Button(button_frame, text="بارگیری محصولات", command=self.load_products)
        refresh_button.grid(row=0, column=0, padx=5)

        add_button = ttk.Button(button_frame, text="افزودن محصول", command=self.add_product_window)
        add_button.grid(row=0, column=1, padx=5)

        edit_button = ttk.Button(button_frame, text="ویرایش محصول", command=self.edit_product_window)
        edit_button.grid(row=0, column=2, padx=5)

        delete_button = ttk.Button(button_frame, text="حذف محصول", command=self.delete_product)
        delete_button.grid(row=0, column=3, padx=5)

        zero_stock_button = ttk.Button(button_frame, text="صفر کردن موجودی‌ها", command=self.zero_out_stock)
        zero_stock_button.grid(row=0, column=4, padx=5)

    def load_products(self):
        try:
            # Add a progress bar
            progress = ttk.Progressbar(self.root, orient=tk.HORIZONTAL, length=100, mode='indeterminate')
            progress.grid(row=1, column=0, sticky=(tk.W, tk.E))
            progress.start()
            self.root.update_idletasks()

            products = self.wcapi.get("products", params={"per_page": 100}).json()

            progress.stop()
            progress.grid_forget()

            for item in self.products_tree.get_children():
                self.products_tree.delete(item)
            for product in products:
                stock = product.get('stock_quantity', 'N/A')
                status = "موجود" if product.get('in_stock', True) else "ناموجود"
                self.products_tree.insert("", "end", values=(product["id"], product["name"], product["price"], stock, status))
        except Exception as e:
            messagebox.showerror("خطا", f"خطا در بارگیری محصولات: {e}")

    def zero_out_stock(self):
        if messagebox.askyesno("تایید", "آیا از صفر کردن موجودی تمام محصولات مطمئن هستید؟"):
            try:
                products = self.wcapi.get("products", params={"per_page": 100}).json()
                for product in products:
                    self.wcapi.put(f"products/{product['id']}", {"stock_quantity": 0, "manage_stock": True})
                self.load_products()
                messagebox.showinfo("موفقیت", "موجودی تمام محصولات با موفقیت صفر شد.")
            except Exception as e:
                messagebox.showerror("خطا", f"خطا در صفر کردن موجودی‌ها: {e}")

    def add_product_window(self):
        self.product_window("add")

    def edit_product_window(self):
        selected_item = self.products_tree.focus()
        if not selected_item:
            messagebox.showwarning("هشدار", "لطفا یک محصول را برای ویرایش انتخاب کنید.")
            return
        self.product_window("edit")

    def product_window(self, mode):
        window = tk.Toplevel(self.root)
        if mode == "add":
            window.title("افزودن محصول جدید")
        else:
            window.title("ویرایش محصول")

        # Get selected product data for editing
        product_data = {}
        if mode == "edit":
            selected_item = self.products_tree.focus()
            item_values = self.products_tree.item(selected_item, "values")
            product_id = item_values[0]
            try:
                product_data = self.wcapi.get(f"products/{product_id}").json()
            except Exception as e:
                messagebox.showerror("Error", f"Failed to fetch product data: {e}")
                window.destroy()
                return

        # Labels and Entries
        labels = ["نام محصول:", "قیمت:", "موجودی:"]
        entries = {}
        for i, label_text in enumerate(labels):
            label = ttk.Label(window, text=label_text)
            label.grid(row=i, column=0, padx=10, pady=5, sticky=tk.W)
            entry = ttk.Entry(window, width=40)
            entry.grid(row=i, column=1, padx=10, pady=5)
            entries[label_text] = entry

        # In Stock Checkbox
        in_stock_var = tk.BooleanVar()
        in_stock_check = ttk.Checkbutton(window, text="موجود", variable=in_stock_var)
        in_stock_check.grid(row=len(labels), column=0, padx=10, pady=5, sticky=tk.W)


        # Populate entries for editing
        if mode == "edit":
            entries["نام محصول:"].insert(0, product_data.get("name", ""))
            entries["قیمت:"].insert(0, product_data.get("price", ""))
            entries["موجودی:"].insert(0, product_data.get("stock_quantity", ""))
            in_stock_var.set(product_data.get("in_stock", True))


        # Save Button
        save_button = ttk.Button(window, text="ذخیره", command=lambda: self.save_product(mode, entries, in_stock_var, product_data.get("id"), window))
        save_button.grid(row=len(labels) + 1, column=0, columnspan=2, pady=10)

    def save_product(self, mode, entries, in_stock_var, product_id, window):
        data = {
            "name": entries["نام محصول:"].get(),
            "regular_price": entries["قیمت:"].get(),
            "stock_quantity": entries["موجودی:"].get(),
            "manage_stock": True,
            "in_stock": in_stock_var.get()
        }

        try:
            if mode == "add":
                self.wcapi.post("products", data).json()
                messagebox.showinfo("موفقیت", "محصول با موفقیت اضافه شد.")
            else:
                self.wcapi.put(f"products/{product_id}", data).json()
                messagebox.showinfo("موفقیت", "محصول با موفقیت ویرایش شد.")

            self.load_products()
            window.destroy()
        except Exception as e:
            messagebox.showerror("خطا", f"خطا در ذخیره محصول: {e}")

    def delete_product(self):
        selected_item = self.products_tree.focus()
        if not selected_item:
            messagebox.showwarning("هشدار", "لطفا یک محصول را برای حذف انتخاب کنید.")
            return

        if messagebox.askyesno("تایید", "آیا از حذف این محصول مطمئن هستید؟"):
            item_values = self.products_tree.item(selected_item, "values")
            product_id = item_values[0]
            try:
                self.wcapi.delete(f"products/{product_id}", params={"force": True})
                self.load_products()
                messagebox.showinfo("موفقیت", "محصول با موفقیت حذف شد.")
            except Exception as e:
                messagebox.showerror("خطا", f"خطا در حذف محصول: {e}")

if __name__ == "__main__":
    root = tk.Tk()
    app = WooCommerceApp(root)
    root.mainloop()
