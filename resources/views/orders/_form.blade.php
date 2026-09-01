@php
    use App\Support\Money;

    $isEdit = $order->exists;

    // Existing rows on edit, old() input after a validation failure, or one
    // blank starter row on a fresh form.
    $itemRows = old('items');

    if ($itemRows === null) {
        $itemRows = $isEdit
            ? $order->items->map(fn ($i) => [
                'product_id'  => $i->product_id,
                'colour_id'   => $i->colour_id,
                'item_name'   => $i->item_name_snapshot,
                'item_colour' => $i->item_colour,
                'quantity'    => $i->quantity,
                'unit_price'  => (float) $i->unit_price,
                'discount'    => (float) $i->discount,
                'notes'       => $i->notes,
            ])->values()->all()
            : [];
    }

    $productData = $products->map(fn ($p) => [
        'id'       => $p->id,
        'name'     => $p->name,
        'code'     => $p->product_code,
        'price'    => (float) $p->default_price,
        'category' => $p->category?->name,
        'colours'  => $p->colours->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'hex' => $c->swatch()])->values(),
    ])->values();

    $allColours = $colours->map(fn ($c) => [
        'id'   => $c->id,
        'name' => $c->name,
        'hex'  => $c->swatch(),
    ])->values();

    $selectedCustomer = old('customer_id', $customer?->id);
@endphp

<form method="POST"
      action="{{ $isEdit ? route('orders.update', $order) : route('orders.store') }}"
      x-data="orderForm({
          items: {{ Js::from($itemRows) }},
          products: {{ Js::from($productData) }},
          allColours: {{ Js::from($allColours) }},
          discount: {{ (float) old('discount', $isEdit ? $order->discount : 0) }},
          deliveryCharge: {{ (float) old('delivery_charge', $isEdit ? $order->delivery_charge : 0) }},
          tax: {{ (float) old('tax', $isEdit ? $order->tax : 0) }},
          paymentStatus: '{{ old('payment_status', $isEdit ? $order->payment_status->value : 'pending') }}',
          amountPaid: {{ (float) old('amount_paid', $isEdit ? $order->amount_paid : 0) }},
          customerId: {{ $selectedCustomer ? (int) $selectedCustomer : 'null' }},
          lookupUrl: '{{ route('orders.lookup.customer') }}',
          searchUrl: '{{ route('orders.lookup.customers') }}',
      })"
      x-on:submit="dirty = false"
      class="space-y-6">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    @if ($errors->any())
        <div class="rounded-2xl border border-danger/30 bg-danger/10 p-4 text-sm text-danger shadow-sm">
            <div class="flex items-center gap-2 font-semibold">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Please fix the following issues before proceeding:</span>
            </div>
            <ul class="mt-2 list-inside list-disc space-y-1 pl-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-3 items-start">
        {{-- Left 2 Columns: Compact Customer & Furniture Items --}}
        <div class="space-y-5 xl:col-span-2">

            {{-- ================= Section A — Customer Details (Compact) ================= --}}
            <div class="rounded-2xl border border-line bg-white p-4 sm:p-5 shadow-sm dark:border-strokedark dark:bg-boxdark"
                 x-data="{ showMoreCustomer: {{ ($customer?->email || $customer?->state || old('customer_email') || old('customer_state')) ? 'true' : 'false' }} }">
                <input type="hidden" name="customer_id" x-model="customerId">

                <div class="flex items-center justify-between border-b border-line/60 pb-3 mb-3.5 dark:border-strokedark">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand/10 text-brand text-sm">
                            <i class="fa-solid fa-user"></i>
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-ink dark:text-white">Customer Details</h3>
                            <p class="text-[11px] text-muted">Phone number checks returning history instantly</p>
                        </div>
                    </div>
                    <button type="button"
                            x-on:click="showMoreCustomer = !showMoreCustomer"
                            class="text-xs font-semibold text-brand hover:underline">
                        <span x-text="showMoreCustomer ? '- Less Details' : '+ More (Email, State)'"></span>
                    </button>
                </div>

                {{-- Returning Customer Matched Banner (Compact) --}}
                <div x-show="duplicate" x-cloak
                     class="mb-3.5 rounded-xl border border-brand/20 bg-brand/5 p-3 transition-all dark:border-brand/30 dark:bg-brand/10">
                    <div class="flex flex-wrap items-center justify-between gap-2.5">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand/15 text-brand text-sm shadow-sm">
                                <i class="fa-solid fa-user-check"></i>
                            </div>
                            <div class="text-xs">
                                <div class="flex items-center gap-1.5 font-bold text-ink dark:text-white">
                                    <span x-text="duplicate?.customer?.name"></span>
                                    <span class="rounded-full bg-brand/20 px-2 py-0.2 text-[10px] text-brand"
                                          x-text="`${duplicate?.orders || 0} Orders`"></span>
                                </div>
                                <div class="text-[11px] text-muted">
                                    Total: <strong class="text-brand" x-text="duplicate?.total_spent"></strong>
                                    <template x-if="duplicate?.last_order">
                                        <span> • Last: <span class="text-ink dark:text-gray-300" x-text="duplicate?.last_order"></span></span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <button type="button"
                                    x-on:click="openOrdersModal()"
                                    class="inline-flex items-center gap-1 rounded-lg border border-brand/40 bg-white px-2.5 py-1 text-xs font-semibold text-brand shadow-sm hover:bg-brand hover:text-white dark:bg-boxdark2">
                                <i class="fa-solid fa-clock-rotate-left text-[11px]"></i>
                                <span>Past Orders</span>
                            </button>
                            <button type="button"
                                    x-on:click="clearCustomer()"
                                    class="rounded-lg px-2 py-1 text-xs text-muted hover:text-danger"
                                    title="Clear fields for new customer">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Primary Customer Grid (Phone, Name, ZIP, Street Address, City) --}}
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-12">
                    <div class="sm:col-span-4">
                        <label class="ta-label text-xs mb-1">Phone Number <span class="text-danger">*</span></label>
                        <div class="relative">
                            <input type="text" name="customer_phone" required maxlength="40"
                                   value="{{ old('customer_phone', $customer?->phone) }}"
                                   x-ref="customerPhone"
                                   placeholder="e.g. +92 300 1234567"
                                   x-on:input.debounce.300ms="checkDuplicate($event.target.value)"
                                   x-on:blur="checkDuplicate($event.target.value)"
                                   class="ta-input text-xs font-semibold py-2 @error('customer_phone') !border-danger @enderror">
                            <div class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted text-xs pointer-events-none">
                                <i class="fa-solid fa-phone text-[10px]"></i>
                            </div>
                        </div>
                        @error('customer_phone')<p class="mt-0.5 text-[11px] text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-5">
                        <label class="ta-label text-xs mb-1">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" name="customer_name" required maxlength="255"
                               value="{{ old('customer_name', $customer?->name) }}"
                               x-ref="customerName"
                               placeholder="e.g. Fatima Malik"
                               class="ta-input text-xs font-medium py-2 @error('customer_name') !border-danger @enderror">
                        @error('customer_name')<p class="mt-0.5 text-[11px] text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-3">
                        <label class="ta-label text-xs mb-1">ZIP / Postal <span class="text-danger">*</span></label>
                        <input type="text" name="customer_zip_code" required maxlength="20"
                               value="{{ old('customer_zip_code', $customer?->zip_code) }}"
                               x-ref="customerZip"
                               placeholder="e.g. 54000"
                               class="ta-input text-xs font-medium py-2 @error('customer_zip_code') !border-danger @enderror">
                        @error('customer_zip_code')<p class="mt-0.5 text-[11px] text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div class="sm:col-span-8">
                        <label class="ta-label text-xs mb-1">Street Address</label>
                        <input type="text" name="customer_address" maxlength="255"
                               value="{{ old('customer_address', $customer?->address) }}"
                               x-ref="customerAddress"
                               placeholder="House/Plot #, Street, Area..."
                               class="ta-input text-xs py-2">
                    </div>

                    <div class="sm:col-span-4">
                        <label class="ta-label text-xs mb-1">City</label>
                        <input type="text" name="customer_city" maxlength="120"
                               value="{{ old('customer_city', $customer?->city) }}"
                               x-ref="customerCity"
                               placeholder="e.g. Lahore"
                               class="ta-input text-xs py-2">
                    </div>
                </div>

                {{-- Secondary Expandable Customer Grid (Email, State) --}}
                <div x-show="showMoreCustomer" x-cloak
                     class="mt-3 pt-3 border-t border-line/60 dark:border-strokedark grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="ta-label text-xs mb-1">Email Address</label>
                        <input type="email" name="customer_email" maxlength="255"
                               value="{{ old('customer_email', $customer?->email) }}"
                               x-ref="customerEmail"
                               placeholder="customer@example.com"
                               class="ta-input text-xs py-2">
                    </div>

                    <div>
                        <label class="ta-label text-xs mb-1">State / Province</label>
                        <input type="text" name="customer_state" maxlength="120"
                               value="{{ old('customer_state', $customer?->state) }}"
                               x-ref="customerState"
                               placeholder="e.g. Punjab"
                               class="ta-input text-xs py-2">
                    </div>
                </div>
            </div>

            {{-- ================= Section B — Furniture Items (Compact Workbench) ================= --}}
            <div class="rounded-2xl border border-line bg-white p-4 sm:p-5 shadow-sm dark:border-strokedark dark:bg-boxdark">
                <div class="flex items-center justify-between border-b border-line/60 pb-3 mb-3.5 dark:border-strokedark">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand/10 text-brand text-sm">
                            <i class="fa-solid fa-couch"></i>
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-ink dark:text-white">Furniture Items</h3>
                            <p class="text-[11px] text-muted">Select catalogue items or type custom items on the fly</p>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary text-xs px-3 py-1.5 shadow-sm font-semibold inline-flex items-center gap-1.5" x-on:click="addItem()">
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Add Item</span>
                    </button>
                </div>

                <div class="space-y-3">
                    <template x-for="(item, index) in items" :key="item.uid">
                        <div class="rounded-xl border border-line/80 bg-surface/30 p-3.5 transition-all dark:border-strokedark dark:bg-boxdark2 hover:border-brand/40">
                            
                            {{-- Item Top Compact Bar --}}
                            <div class="mb-2 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-brand text-[10px] font-black text-white"
                                          x-text="index + 1"></span>
                                    
                                    <template x-if="item.product_id">
                                        <span class="inline-flex items-center gap-1 rounded bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">
                                            <i class="fa-solid fa-check text-[9px]"></i>
                                            <span>SKU: <strong x-text="product(item.product_id)?.code"></strong></span>
                                        </span>
                                    </template>

                                    <template x-if="!item.product_id && item.item_name.trim()">
                                        <span class="inline-flex items-center gap-1 rounded bg-blue-500/10 px-2 py-0.5 text-[10px] font-semibold text-blue-600 dark:text-blue-400">
                                            <i class="fa-solid fa-sparkles text-[9px]"></i>
                                            <span>New Product</span>
                                        </span>
                                    </template>
                                </div>

                                <div class="flex items-center gap-1">
                                    <button type="button" x-on:click="duplicateItem(index)"
                                            class="inline-flex h-6 w-6 items-center justify-center rounded text-muted hover:text-brand"
                                            title="Duplicate item">
                                        <i class="fa-regular fa-copy text-[11px]"></i>
                                    </button>
                                    <template x-if="items.length > 1">
                                        <button type="button" x-on:click="removeItem(index)"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded text-muted hover:text-danger"
                                                title="Remove item">
                                            <i class="fa-solid fa-trash-can text-[11px]"></i>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            {{-- Dense 1-Row Grid for Item Inputs --}}
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-center">

                                {{-- Product Name Autocomplete --}}
                                <div class="sm:col-span-5 relative">
                                    <input type="hidden" :name="`items[${index}][product_id]`" x-model="item.product_id">
                                    
                                    <div class="relative">
                                        <input type="text"
                                               class="ta-input text-xs font-medium py-1.5 pr-7"
                                               :name="`items[${index}][item_name]`"
                                               x-model="item.item_name"
                                               placeholder="Product name..."
                                               autocomplete="off"
                                               x-on:focus="item.showProductMenu = true"
                                               x-on:input="onItemNameInput(item)"
                                               x-on:keydown.escape="item.showProductMenu = false">
                                        
                                        <button type="button"
                                                x-on:click="item.showProductMenu = !item.showProductMenu"
                                                class="absolute right-2 top-1/2 -translate-y-1/2 text-muted hover:text-ink">
                                            <i class="fa-solid fa-chevron-down text-[10px] transition duration-150"
                                               :class="{ 'rotate-180': item.showProductMenu }"></i>
                                        </button>
                                    </div>

                                    {{-- Product Dropdown Menu --}}
                                    <div x-show="item.showProductMenu"
                                         x-cloak
                                         x-on:click.outside="item.showProductMenu = false"
                                         class="absolute z-50 mt-1 max-h-52 w-full overflow-y-auto rounded-xl border border-line bg-white p-1 shadow-2xl dark:border-strokedark dark:bg-boxdark2">
                                        
                                        <template x-for="p in filteredProducts(item.item_name)" :key="p.id">
                                            <button type="button"
                                                    x-on:click="selectProduct(item, p)"
                                                    class="flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-left text-xs transition hover:bg-brand/10 dark:hover:bg-boxdark">
                                                <div>
                                                    <div class="font-semibold text-ink dark:text-white" x-text="p.name"></div>
                                                    <div class="text-[10px] text-muted">
                                                        <span class="font-mono" x-text="p.code"></span>
                                                        <span x-show="p.category" x-text="` • ${p.category}`"></span>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="font-bold text-brand" x-text="money(p.price)"></div>
                                                </div>
                                            </button>
                                        </template>

                                        <div x-show="item.item_name.trim() && !hasExactProductMatch(item.item_name)"
                                             class="border-t border-line/60 p-1.5 text-xs text-muted dark:border-strokedark">
                                            <button type="button"
                                                    x-on:click="item.product_id = ''; item.showProductMenu = false"
                                                    class="flex w-full items-center gap-1.5 rounded-lg bg-brand/5 p-1.5 font-semibold text-brand text-[11px] hover:bg-brand/10">
                                                <i class="fa-solid fa-sparkles text-[10px]"></i>
                                                <span>Save "<strong><span x-text="item.item_name"></span></strong>" as new product</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Colour Combobox --}}
                                <div class="sm:col-span-3 relative">
                                    <input type="hidden" :name="`items[${index}][colour_id]`" x-model="item.colour_id">

                                    <div class="relative">
                                        <input type="text"
                                               class="ta-input text-xs font-medium py-1.5 pl-7 pr-6"
                                               :name="`items[${index}][item_colour]`"
                                               x-model="item.item_colour"
                                               placeholder="Colour..."
                                               autocomplete="off"
                                               x-on:focus="item.showColourMenu = true"
                                               x-on:input="onColourInput(item)"
                                               x-on:keydown.escape="item.showColourMenu = false">

                                        <div class="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2">
                                            <span class="block h-3.5 w-3.5 rounded-full border border-black/15 shadow-sm"
                                                  :style="`background-color: ${currentColourHex(item)}`"></span>
                                        </div>

                                        <button type="button"
                                                x-on:click="item.showColourMenu = !item.showColourMenu"
                                                class="absolute right-2 top-1/2 -translate-y-1/2 text-muted hover:text-ink">
                                            <i class="fa-solid fa-chevron-down text-[10px] transition duration-150"
                                               :class="{ 'rotate-180': item.showColourMenu }"></i>
                                        </button>
                                    </div>

                                    {{-- Colour Dropdown Menu --}}
                                    <div x-show="item.showColourMenu"
                                         x-cloak
                                         x-on:click.outside="item.showColourMenu = false"
                                         class="absolute z-50 mt-1 max-h-48 w-full overflow-y-auto rounded-xl border border-line bg-white p-1 shadow-2xl dark:border-strokedark dark:bg-boxdark2">
                                        
                                        <template x-for="c in filteredColours(item)" :key="c.id">
                                            <button type="button"
                                                    x-on:click="selectColour(item, c)"
                                                    class="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-xs transition hover:bg-brand/10 dark:hover:bg-boxdark">
                                                <span class="h-3.5 w-3.5 rounded-full border border-black/15"
                                                      :style="`background-color: ${c.hex || '#98A2B3'}`"></span>
                                                <span class="text-ink dark:text-white" x-text="c.name"></span>
                                            </button>
                                        </template>

                                        <div x-show="item.item_colour.trim() && !hasExactColourMatch(item)"
                                             class="border-t border-line/60 p-1.5 text-xs text-muted dark:border-strokedark">
                                            <button type="button"
                                                    x-on:click="item.colour_id = ''; item.showColourMenu = false"
                                                    class="flex w-full items-center gap-1.5 rounded-lg bg-brand/5 p-1.5 font-semibold text-brand text-[11px] hover:bg-brand/10">
                                                <i class="fa-solid fa-palette text-[10px]"></i>
                                                <span>Save "<strong><span x-text="item.item_colour"></span></strong>" as new colour</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Quantity --}}
                                <div class="col-span-4 sm:col-span-1">
                                    <input type="number" min="1" step="1" class="ta-input text-xs font-semibold py-1.5 text-center px-1"
                                           placeholder="Qty"
                                           :name="`items[${index}][quantity]`" x-model.number="item.quantity">
                                </div>

                                {{-- Unit Price --}}
                                <div class="col-span-8 sm:col-span-3">
                                    <input type="number" min="0" step="0.01" class="ta-input text-xs font-semibold py-1.5"
                                           placeholder="Price"
                                           :name="`items[${index}][unit_price]`" x-model.number="item.unit_price">
                                </div>

                                <input type="hidden" :name="`items[${index}][discount]`" value="0">
                            </div>

                            {{-- Line Total & Optional Note in 1 Subtle Sub-Row --}}
                            <div class="mt-2 pt-2 border-t border-line/40 dark:border-strokedark flex items-center justify-between text-[11px]">
                                <input type="text"
                                       class="w-3/5 bg-transparent border-none p-0 text-muted placeholder:text-muted/60 text-[11px] focus:ring-0 focus:outline-none"
                                       :name="`items[${index}][notes]`"
                                       x-model="item.notes"
                                       placeholder="+ Add item note (dimensions, fabric, etc)...">
                                <div class="font-bold text-brand">
                                    <span class="text-muted font-normal">Line: </span>
                                    <span x-text="money(lineTotal(item))"></span>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- Empty Items State --}}
                    <div x-show="!items.length" x-cloak
                         class="rounded-xl border border-dashed border-line p-6 text-center dark:border-strokedark">
                        <i class="fa-solid fa-couch text-xl text-brand mb-1"></i>
                        <h4 class="text-xs font-bold text-ink dark:text-white">No items added yet</h4>
                        <button type="button" class="btn btn-primary btn-sm mt-2 text-xs" x-on:click="addItem()">
                            <i class="fa-solid fa-plus mr-1"></i> Add First Item
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= Right column: Unified Compact Sticky Summary & Actions ================= --}}
        <div class="space-y-4 xl:sticky xl:top-20">
            
            <div class="rounded-2xl border border-line bg-white p-4 sm:p-5 shadow-sm dark:border-strokedark dark:bg-boxdark space-y-4">
                
                {{-- Grand Total Spotlight --}}
                <div class="rounded-xl border border-brand/20 bg-brand/5 p-4 text-center dark:border-brand/30 dark:bg-brand/10">
                    <span class="block text-[11px] font-bold uppercase tracking-wider text-muted">Grand Total</span>
                    <span class="mt-0.5 block text-3xl font-black text-brand tracking-tight" x-text="money(grandTotal)"></span>
                    <div class="mt-1 text-[11px] text-muted font-medium">
                        <span x-text="items.length"></span> item(s) • Total sum of items
                    </div>
                </div>

                {{-- Sales Person Selection (Mandatory) --}}
                <div>
                    <label class="ta-label text-xs mb-1">Sales Person <span class="text-danger">*</span></label>
                    <select name="sales_person_id" required class="ta-input text-xs font-semibold py-2 @error('sales_person_id') !border-danger @enderror">
                        <option value="">-- Choose Sales Person --</option>
                        @foreach ($salesPersons as $person)
                            <option value="{{ $person->id }}"
                                @selected((int) old('sales_person_id', $order->sales_person_id) === $person->id)>
                                {{ $person->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('sales_person_id')<p class="mt-0.5 text-[11px] text-danger">{{ $message }}</p>@enderror
                </div>

                {{-- Delivery Target Date --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-semibold text-ink dark:text-gray-200">
                            Delivery Date <span class="text-danger">*</span>
                        </label>
                        <div class="flex items-center gap-1">
                            <button type="button" x-on:click="setDeliveryDays(3)" class="rounded bg-surface px-1.5 py-0.5 text-[10px] font-semibold text-ink hover:text-brand dark:bg-boxdark2">+3d</button>
                            <button type="button" x-on:click="setDeliveryDays(7)" class="rounded bg-surface px-1.5 py-0.5 text-[10px] font-semibold text-ink hover:text-brand dark:bg-boxdark2">+7d</button>
                            <button type="button" x-on:click="setDeliveryDays(14)" class="rounded bg-surface px-1.5 py-0.5 text-[10px] font-semibold text-ink hover:text-brand dark:bg-boxdark2">+14d</button>
                        </div>
                    </div>

                    <input type="date" name="requested_delivery_date" required
                           @unless ($isEdit) min="{{ today()->toDateString() }}" @endunless
                           x-ref="deliveryDate"
                           value="{{ old('requested_delivery_date', optional($order->requested_delivery_date)->format('Y-m-d')) }}"
                           class="ta-input text-xs font-medium py-2 @error('requested_delivery_date') !border-danger @enderror">
                    @error('requested_delivery_date')<p class="mt-0.5 text-[11px] text-danger">{{ $message }}</p>@enderror
                </div>

                {{-- Hidden Fields for System Requirements --}}
                <input type="hidden" name="discount" value="0">
                <input type="hidden" name="delivery_charge" value="0">
                <input type="hidden" name="tax" value="0">
                <input type="hidden" name="payment_status" value="{{ old('payment_status', $isEdit ? $order->payment_status->value : 'pending') }}">
                <input type="hidden" name="payment_method" value="{{ old('payment_method', $order->payment_method?->value) }}">
                <input type="hidden" name="amount_paid" value="{{ old('amount_paid', $isEdit ? $order->amount_paid : 0) }}">

                {{-- Expandable Order Notes & Status --}}
                <div x-data="{ showOptions: {{ ($isEdit || old('notes')) ? 'true' : 'false' }} }" class="pt-2 border-t border-line/60 dark:border-strokedark">
                    <button type="button" x-on:click="showOptions = !showOptions" class="flex w-full items-center justify-between text-xs font-semibold text-muted hover:text-ink">
                        <span>Status &amp; Notes</span>
                        <i class="fa-solid fa-chevron-down text-[10px] transition" :class="{ 'rotate-180': showOptions }"></i>
                    </button>

                    <div x-show="showOptions" x-cloak class="mt-3 space-y-3">
                        <div>
                            <label class="ta-label text-xs mb-1">Order Status</label>
                            <select name="order_status" class="ta-input text-xs py-2">
                                @foreach ($statuses as $status)
                                    @continue($status === \App\Enums\OrderStatus::Cancelled && ! $isEdit)
                                    <option value="{{ $status->value }}"
                                        @selected(old('order_status', $order->order_status?->value ?? 'new') === $status->value)>
                                        {{ $status->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        @if ($isEdit)
                            <div>
                                <label class="ta-label text-xs mb-1">Actual Delivery Date</label>
                                <input type="date" name="actual_delivery_date" max="{{ today()->toDateString() }}"
                                       value="{{ old('actual_delivery_date', optional($order->actual_delivery_date)->format('Y-m-d')) }}"
                                       class="ta-input text-xs py-2 @error('actual_delivery_date') !border-danger @enderror">
                            </div>
                        @endif

                        <div>
                            <label class="ta-label text-xs mb-1">Notes / Instructions</label>
                            <textarea name="notes" rows="2" maxlength="2000" class="ta-input text-xs py-1.5"
                                      placeholder="Gate code, floor details, etc.">{{ old('notes', $order->notes) }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="pt-2 flex flex-col gap-2">
                    <button type="submit" class="btn btn-primary w-full py-2.5 text-sm font-bold shadow-md shadow-brand/20">
                        <i class="fa-solid fa-floppy-disk mr-1.5"></i> {{ $isEdit ? 'Save Order Changes' : 'Create & Confirm Order' }}
                    </button>
                    <a href="{{ $isEdit ? route('orders.show', $order) : route('orders.index') }}"
                       class="btn btn-light w-full py-2 text-xs font-semibold">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- ================= Previous Orders Modal Popup ================= --}}
    <div x-show="showOrdersModal"
         x-cloak
         x-transition.opacity.duration.200ms
         x-on:keydown.escape.window="closeOrdersModal()"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-black/60 backdrop-blur-sm">
        
        <div x-on:click.outside="closeOrdersModal()"
             class="relative flex max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl border border-line bg-white shadow-2xl dark:border-strokedark dark:bg-boxdark">
            
            {{-- Modal Header --}}
            <div class="flex items-center justify-between border-b border-line px-6 py-4 dark:border-strokedark">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand/10 text-brand">
                        <i class="fa-solid fa-clock-rotate-left text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink dark:text-white">
                            Order History — <span x-text="duplicate?.customer?.name"></span>
                        </h3>
                        <p class="text-xs text-muted">
                            Phone: <span class="font-medium text-ink dark:text-gray-300" x-text="duplicate?.customer?.phone"></span>
                            • Total Spent: <strong class="text-brand" x-text="duplicate?.total_spent"></strong>
                            • Total Orders: <strong class="text-ink dark:text-white" x-text="duplicate?.orders"></strong>
                        </p>
                    </div>
                </div>
                <button type="button" x-on:click="closeOrdersModal()"
                        class="rounded-lg p-2 text-muted transition hover:bg-surface hover:text-ink dark:hover:bg-boxdark2 dark:hover:text-white">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            {{-- Modal Body: Orders List --}}
            <div class="overflow-y-auto p-6 space-y-4">
                <template x-if="!duplicate?.orders_list || !duplicate?.orders_list.length">
                    <div class="py-12 text-center text-muted">
                        <i class="fa-solid fa-box-open text-3xl mb-2 opacity-40"></i>
                        <p class="text-sm font-medium">No previous orders found for this customer.</p>
                    </div>
                </template>

                <template x-for="ord in (duplicate?.orders_list || [])" :key="ord.id">
                    <div class="rounded-xl border border-line bg-surface/50 p-4 transition hover:border-brand/40 dark:border-strokedark dark:bg-boxdark2">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/60 pb-2.5 dark:border-strokedark">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-sm font-bold text-brand" x-text="ord.order_number"></span>
                                <span class="rounded bg-brand/10 px-2 py-0.5 text-[11px] font-semibold text-brand" x-text="ord.order_status"></span>
                                <span class="rounded bg-surface px-2 py-0.5 text-[11px] font-medium text-muted border border-line dark:border-strokedark" x-text="`Payment: ${ord.payment_status}`"></span>
                            </div>
                            <div class="text-right">
                                <span class="text-base font-bold text-ink dark:text-white" x-text="ord.grand_total"></span>
                            </div>
                        </div>

                        <div class="mt-2.5 flex flex-wrap items-center justify-between text-xs text-muted">
                            <div>
                                <span>Ordered on: <strong class="text-ink dark:text-gray-200" x-text="ord.created_at"></strong></span>
                                <template x-if="ord.requested_delivery_date">
                                    <span class="ml-2">• Delivery target: <strong class="text-ink dark:text-gray-200" x-text="ord.requested_delivery_date"></strong></span>
                                </template>
                            </div>
                            <div>
                                <span class="font-medium text-ink dark:text-gray-300" x-text="`${ord.total_quantity} item(s)`"></span>
                            </div>
                        </div>

                        {{-- Line items breakdown --}}
                        <div class="mt-3 space-y-1.5 border-t border-line/40 pt-2.5 dark:border-strokedark">
                            <template x-for="(itm, itmIdx) in ord.items" :key="itmIdx">
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-couch text-[10px] text-brand"></i>
                                        <span class="font-semibold text-ink dark:text-gray-200" x-text="`${itm.quantity}x ${itm.name}`"></span>
                                        <template x-if="itm.colour">
                                            <span class="text-muted" x-text="`(${itm.colour})`"></span>
                                        </template>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-muted" x-text="`${itm.quantity} × ${itm.unit_price}`"></span>
                                        <span class="font-medium text-ink dark:text-white" x-text="itm.line_total"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Modal Footer --}}
            <div class="flex items-center justify-between border-t border-line px-6 py-3.5 bg-surface/30 dark:border-strokedark dark:bg-boxdark2">
                <span class="text-xs text-muted">Press ESC or click anywhere outside to close</span>
                <button type="button" x-on:click="closeOrdersModal()" class="btn btn-primary text-xs px-4 py-2">
                    Continue With Order
                </button>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
    function orderForm(config) {
        return {
            items: [],
            products: config.products || [],
            allColours: config.allColours || [],
            discount: config.discount,
            deliveryCharge: config.deliveryCharge,
            tax: config.tax,
            paymentStatus: config.paymentStatus,
            amountPaid: config.amountPaid,
            customerId: config.customerId,
            duplicate: null,
            showOrdersModal: false,
            dirty: false,

            init() {
                this.items = (config.items || []).map((row) => this.normalise(row));
                if (!this.items.length) this.addItem();

                if (this.$refs.customerPhone && this.$refs.customerPhone.value) {
                    this.checkDuplicate(this.$refs.customerPhone.value);
                }
            },

            openOrdersModal() {
                this.showOrdersModal = true;
            },

            closeOrdersModal() {
                this.showOrdersModal = false;
            },

            normalise(row) {
                return {
                    uid: Math.random().toString(36).slice(2),
                    product_id: row.product_id ? Number(row.product_id) : '',
                    colour_id: row.colour_id ? Number(row.colour_id) : '',
                    item_name: row.item_name || '',
                    item_colour: row.item_colour || '',
                    quantity: Number(row.quantity) || 1,
                    unit_price: Number(row.unit_price) || 0,
                    discount: Number(row.discount) || 0,
                    notes: row.notes || '',
                    showProductMenu: false,
                    showColourMenu: false,
                };
            },

            addItem() {
                this.items.push(this.normalise({}));
                this.dirty = true;
            },

            duplicateItem(index) {
                const source = this.items[index];
                if (!source) return;
                this.items.splice(index + 1, 0, this.normalise({
                    product_id: source.product_id,
                    colour_id: source.colour_id,
                    item_name: source.item_name,
                    item_colour: source.item_colour,
                    quantity: source.quantity,
                    unit_price: source.unit_price,
                    discount: source.discount,
                    notes: source.notes,
                }));
                this.dirty = true;
            },

            removeItem(index) {
                this.items.splice(index, 1);
                this.dirty = true;
            },

            product(id) {
                return this.products.find((p) => p.id === Number(id));
            },

            filteredProducts(query) {
                const term = (query || '').toLowerCase().trim();
                if (!term) return this.products.slice(0, 15);
                return this.products.filter(p =>
                    p.name.toLowerCase().includes(term) ||
                    (p.code && p.code.toLowerCase().includes(term)) ||
                    (p.category && p.category.toLowerCase().includes(term))
                ).slice(0, 15);
            },

            hasExactProductMatch(name) {
                const term = (name || '').toLowerCase().trim();
                return this.products.some(p => p.name.toLowerCase().trim() === term);
            },

            onItemNameInput(item) {
                item.showProductMenu = true;
                const matched = this.products.find(p => p.name.toLowerCase().trim() === item.item_name.toLowerCase().trim());
                if (matched) {
                    item.product_id = matched.id;
                    if (!item.unit_price) item.unit_price = matched.price;
                } else {
                    item.product_id = '';
                }
            },

            selectProduct(item, prod) {
                item.product_id = prod.id;
                item.item_name = prod.name;
                item.unit_price = prod.price || item.unit_price || 0;
                item.showProductMenu = false;

                // Adjust colour selection if the product has a specific shortlist
                if (prod.colours && prod.colours.length) {
                    const shortlistIds = prod.colours.map(c => c.id);
                    if (item.colour_id && !shortlistIds.includes(Number(item.colour_id))) {
                        item.colour_id = prod.colours[0].id;
                        item.item_colour = prod.colours[0].name;
                    }
                }
            },

            filteredColours(item) {
                const pool = this.coloursFor(item);
                const term = (item.item_colour || '').toLowerCase().trim();
                if (!term) return pool;
                return pool.filter(c => c.name.toLowerCase().includes(term));
            },

            coloursFor(item) {
                const prod = this.product(item.product_id);
                return (prod && prod.colours && prod.colours.length) ? prod.colours : this.allColours;
            },

            hasExactColourMatch(item) {
                const term = (item.item_colour || '').toLowerCase().trim();
                return this.allColours.some(c => c.name.toLowerCase().trim() === term);
            },

            onColourInput(item) {
                item.showColourMenu = true;
                const matched = this.allColours.find(c => c.name.toLowerCase().trim() === item.item_colour.toLowerCase().trim());
                if (matched) {
                    item.colour_id = matched.id;
                } else {
                    item.colour_id = '';
                }
            },

            selectColour(item, colour) {
                item.colour_id = colour.id;
                item.item_colour = colour.name;
                item.showColourMenu = false;
            },

            currentColourHex(item) {
                if (item.colour_id) {
                    const found = this.allColours.find(c => c.id === Number(item.colour_id));
                    if (found && found.hex) return found.hex;
                }
                const matchByName = this.allColours.find(c => c.name.toLowerCase().trim() === (item.item_colour || '').toLowerCase().trim());
                if (matchByName && matchByName.hex) return matchByName.hex;
                return '#98A2B3';
            },

            lineTotal(item) {
                const value = (Number(item.quantity) || 0) * (Number(item.unit_price) || 0);
                return Math.max(0, value - (Number(item.discount) || 0));
            },

            get subtotal() {
                return this.items.reduce((sum, item) => sum + this.lineTotal(item), 0);
            },

            get grandTotal() {
                const total = this.subtotal
                    - (Number(this.discount) || 0)
                    + (Number(this.deliveryCharge) || 0)
                    + (Number(this.tax) || 0);
                return Math.max(0, total);
            },

            get balanceDue() {
                const paid = this.paymentStatus === 'paid' ? this.grandTotal
                    : this.paymentStatus === 'partial' ? (Number(this.amountPaid) || 0)
                    : 0;
                return Math.max(0, this.grandTotal - paid);
            },

            setDeliveryDays(days) {
                const d = new Date();
                d.setDate(d.getDate() + days);
                const iso = d.toISOString().split('T')[0];
                if (this.$refs.deliveryDate) {
                    this.$refs.deliveryDate.value = iso;
                }
            },

            money(value) {
                return window.crmMoney(value);
            },

            clearCustomer() {
                this.customerId = null;
                this.duplicate = null;
                if (this.$refs.customerPhone) this.$refs.customerPhone.value = '';
                if (this.$refs.customerName) this.$refs.customerName.value = '';
                if (this.$refs.customerEmail) this.$refs.customerEmail.value = '';
                if (this.$refs.customerAddress) this.$refs.customerAddress.value = '';
                if (this.$refs.customerCity) this.$refs.customerCity.value = '';
                if (this.$refs.customerState) this.$refs.customerState.value = '';
                if (this.$refs.customerZip) this.$refs.customerZip.value = '';
            },

            async checkDuplicate(phone) {
                const clean = (phone || '').replace(/\D/g, '');
                if (clean.length < 6) {
                    this.duplicate = null;
                    return;
                }

                try {
                    const response = await fetch(`${config.lookupUrl}?phone=${encodeURIComponent(phone)}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await response.json();
                    if (data.found) {
                        this.duplicate = data;
                        this.customerId = data.customer.id;

                        // Autofill customer inputs if empty or matching duplicate
                        if (this.$refs.customerName && (!this.$refs.customerName.value || this.$refs.customerName.value === data.customer.name)) {
                            this.$refs.customerName.value = data.customer.name || '';
                        }
                        if (this.$refs.customerEmail && (!this.$refs.customerEmail.value || this.$refs.customerEmail.value === data.customer.email)) {
                            this.$refs.customerEmail.value = data.customer.email || '';
                        }
                        if (this.$refs.customerAddress && (!this.$refs.customerAddress.value || this.$refs.customerAddress.value === data.customer.address)) {
                            this.$refs.customerAddress.value = data.customer.address || '';
                        }
                        if (this.$refs.customerCity && (!this.$refs.customerCity.value || this.$refs.customerCity.value === data.customer.city)) {
                            this.$refs.customerCity.value = data.customer.city || '';
                        }
                        if (this.$refs.customerState && (!this.$refs.customerState.value || this.$refs.customerState.value === data.customer.state)) {
                            this.$refs.customerState.value = data.customer.state || '';
                        }
                        if (this.$refs.customerZip && (!this.$refs.customerZip.value || this.$refs.customerZip.value === data.customer.zip_code)) {
                            this.$refs.customerZip.value = data.customer.zip_code || '';
                        }
                    } else {
                        this.duplicate = null;
                    }
                } catch (e) {
                    this.duplicate = null;
                }
            },
        };
    }

    // Mirrors the server-side currency settings so the live totals match the saved order exactly.
    window.crmMoney = function (value) {
        const symbol   = @json(Money::symbol());
        const decimals = @json(Money::decimals());
        const after    = @json(Money::symbolAfter());
        const number   = Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });

        if (!symbol) return number;
        return after ? `${number} ${symbol}` : `${symbol} ${number}`;
    };
</script>
@endpush
