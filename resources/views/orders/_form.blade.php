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
    $selectedSalesPerson = (int) old('sales_person_id', $order->sales_person_id ?: (auth()->user()?->isSalesPerson() ? auth()->id() : 0));
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
          customerId: {{ $selectedCustomer ? (int) $selectedCustomer : 'null' }},
          lookupUrl: '{{ route('orders.lookup.customer') }}',
          searchUrl: '{{ route('orders.lookup.customers') }}',
      })"
      x-on:submit="dirty = false"
      class="space-y-4">
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

    {{-- Single Unified Order Form Container --}}
    <div class="rounded-2xl border border-line bg-white p-4 sm:p-6 shadow-xs dark:border-strokedark dark:bg-boxdark space-y-6"
         x-data="{ 
             showMoreCustomer: {{ ($customer?->email || $customer?->state || $customer?->phone_alt || old('customer_email') || old('customer_state') || old('customer_phone_alt')) ? 'true' : 'false' }},
             {{-- Order status and delivery notes are shown expanded by default. --}}
             showMoreOptions: true
         }">
        
        <input type="hidden" name="customer_id" x-model="customerId">
        <input type="hidden" name="discount" value="0">
        <input type="hidden" name="delivery_charge" value="0">
        <input type="hidden" name="tax" value="0">

        {{-- ========================================================================= --}}
        {{-- 1. Customer & Order Basic Details --}}
        {{-- ========================================================================= --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-line/60 dark:border-strokedark">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand/10 text-brand text-xs font-bold">
                        <i class="fa-solid fa-user"></i>
                    </span>
                    <h3 class="text-base font-bold text-ink dark:text-white">Customer &amp; Order Details</h3>
                </div>
                <button type="button"
                        x-on:click="showMoreCustomer = !showMoreCustomer"
                        class="text-xs font-semibold text-brand hover:underline transition">
                    <span x-text="showMoreCustomer ? '− Hide Extra Details' : '+ Add Phone / Email / State'"></span>
                </button>
            </div>

            {{-- Returning Customer Matched Notification --}}
            <div x-show="duplicate" x-cloak
                 class="rounded-xl border border-brand/30 bg-brand/5 p-3.5 transition-all dark:border-brand/40 dark:bg-brand/10">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand/15 text-brand font-bold text-sm">
                            <i class="fa-solid fa-user-check"></i>
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-ink dark:text-white text-sm" x-text="duplicate?.customer?.name"></span>
                                <span class="rounded-full bg-brand/15 px-2 py-0.5 text-[10px] font-bold text-brand">Existing Customer</span>
                            </div>
                            <p class="text-xs text-muted">
                                <span class="font-semibold text-brand" x-text="`${duplicate?.orders || 0} past orders`"></span>
                                <span x-show="duplicate?.total_spent" x-text="` · Spent: ${duplicate?.total_spent}`"></span>
                                <span x-show="duplicate?.last_order" x-text="` · Last: ${duplicate?.last_order}`"></span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button"
                                x-on:click="openOrdersModal()"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-brand bg-brand px-3 py-1.5 text-xs font-bold text-white shadow-xs hover:bg-brand-600 transition">
                            <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                            <span>View History</span>
                        </button>
                        <button type="button"
                                x-on:click="clearCustomer()"
                                class="rounded-lg p-1.5 text-muted hover:text-danger hover:bg-surface transition"
                                title="Clear customer fields">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Row 1: Phone & Name --}}
            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-12">
                <div class="sm:col-span-5">
                    <label class="text-sm font-semibold text-muted block mb-1">Phone Number <span class="text-danger">*</span></label>
                    <div class="relative">
                        <input type="text" name="customer_phone" required maxlength="40"
                               value="{{ old('customer_phone', $customer?->phone) }}"
                               x-ref="customerPhone"
                               placeholder="Phone number"
                               x-on:input.debounce.350ms="checkDuplicate($event.target.value)"
                               x-on:change="checkDuplicate($event.target.value)"
                               class="ta-input !py-2 font-semibold @error('customer_phone') !border-danger @enderror">
                    </div>
                    @error('customer_phone')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-7">
                    <label class="text-sm font-semibold text-muted block mb-1">Customer Name <span class="text-danger">*</span></label>
                    <input type="text" name="customer_name" required maxlength="255"
                           value="{{ old('customer_name', $customer?->name) }}"
                           x-ref="customerName"
                           placeholder="Customer full name"
                           class="ta-input !py-2 font-medium @error('customer_name') !border-danger @enderror">
                    @error('customer_name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Row 2: Address & Postal Code. The postal code sits beside the
                 address rather than in its own row, since together they are the
                 delivery location and the postal code drives the ZIP reports. --}}
            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-12">
                <div class="sm:col-span-8">
                    <label class="text-sm font-semibold text-muted block mb-1">Delivery Address</label>
                    <input type="text" name="customer_address" maxlength="255"
                           value="{{ old('customer_address', $customer?->address) }}"
                           x-ref="customerAddress"
                           placeholder="Street address, building, apartment..."
                           class="ta-input !py-2">
                </div>

                <div class="sm:col-span-4">
                    <label class="text-sm font-semibold text-muted block mb-1">Postal / ZIP Code <span class="text-danger">*</span></label>
                    <input type="text" name="customer_zip_code" required maxlength="20"
                           value="{{ old('customer_zip_code', $customer?->zip_code) }}"
                           x-ref="customerZip"
                           placeholder="Postal code"
                           class="ta-input !py-2 font-medium @error('customer_zip_code') !border-danger @enderror">
                    @error('customer_zip_code')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Expandable Row: Alternate phone, Email & State --}}
            <div x-show="showMoreCustomer" x-cloak
                 class="pt-2 grid grid-cols-1 gap-3.5 sm:grid-cols-3">
                <div>
                    <label class="text-sm font-semibold text-muted block mb-1">
                        Additional Phone <span class="font-normal text-muted">(optional)</span>
                    </label>
                    <input type="text" name="customer_phone_alt" maxlength="40"
                           value="{{ old('customer_phone_alt', $customer?->phone_alt) }}"
                           x-ref="customerPhoneAlt"
                           placeholder="Second contact number"
                           class="ta-input !py-2 @error('customer_phone_alt') !border-danger @enderror">
                    @error('customer_phone_alt')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-sm font-semibold text-muted block mb-1">Email Address</label>
                    <input type="email" name="customer_email" maxlength="255"
                           value="{{ old('customer_email', $customer?->email) }}"
                           x-ref="customerEmail"
                           placeholder="email@example.com"
                           class="ta-input !py-2">
                </div>

                <div>
                    <label class="text-sm font-semibold text-muted block mb-1">State / Province</label>
                    <input type="text" name="customer_state" maxlength="120"
                           value="{{ old('customer_state', $customer?->state) }}"
                           x-ref="customerState"
                           placeholder="State / Province"
                           class="ta-input !py-2">
                </div>
            </div>

            {{-- Row 3: Sales Person, Order Date, Target Delivery Date --}}
            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-12 pt-1">
                {{-- Sales Person --}}
                <div class="sm:col-span-4">
                    <label class="text-sm font-semibold text-muted block mb-1">Sales Person <span class="text-danger">*</span></label>
                    <select name="sales_person_id" required class="ta-input !py-2 font-semibold @error('sales_person_id') !border-danger @enderror">
                        <option value="">-- Select Sales Person --</option>
                        @foreach ($salesPersons as $person)
                            <option value="{{ $person->id }}"
                                @selected($selectedSalesPerson === $person->id)>
                                {{ $person->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('sales_person_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                {{-- Order Date --}}
                <div class="sm:col-span-4">
                    <label class="text-sm font-semibold text-muted block mb-1">Order Date <span class="text-danger">*</span></label>
                    <input type="text" name="order_created_at" required
                           x-datepicker
                           value="{{ old('order_created_at', optional($order->order_created_at)->format('Y-m-d') ?: today()->format('Y-m-d')) }}"
                           class="ta-input !py-2 font-medium @error('order_created_at') !border-danger @enderror">
                    @error('order_created_at')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                {{-- Delivery Date --}}
                <div class="sm:col-span-4">
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-semibold text-muted block">Delivery Date <span class="text-danger">*</span></label>
                        <div class="flex items-center gap-1">
                            <button type="button" x-on:click="setDeliveryDays(1)" class="rounded bg-surface px-1.5 py-0.5 text-[11px] font-semibold text-brand hover:bg-brand/10 dark:bg-boxdark2">Tomorrow</button>
                            <button type="button" x-on:click="setDeliveryDays(2)" class="rounded bg-surface px-1.5 py-0.5 text-[11px] font-semibold text-ink hover:text-brand dark:bg-boxdark2">+2d</button>
                            <button type="button" x-on:click="setDeliveryDays(3)" class="rounded bg-surface px-1.5 py-0.5 text-[11px] font-semibold text-ink hover:text-brand dark:bg-boxdark2">+3d</button>
                            <button type="button" x-on:click="setDeliveryDays(7)" class="rounded bg-surface px-1.5 py-0.5 text-[11px] font-semibold text-ink hover:text-brand dark:bg-boxdark2">+7d</button>
                        </div>
                    </div>
                    <input type="text" name="requested_delivery_date" required
                           x-ref="deliveryDate"
                           x-datepicker
                           value="{{ old('requested_delivery_date', optional($order->requested_delivery_date)->format('Y-m-d') ?: today()->addDay()->format('Y-m-d')) }}"
                           class="ta-input !py-2 font-medium @error('requested_delivery_date') !border-danger @enderror">
                    @error('requested_delivery_date')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Optional Notes & Status Toggle --}}
            <div class="pt-1">
                <button type="button" x-on:click="showMoreOptions = !showMoreOptions"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted hover:text-brand transition">
                    <i class="fa-solid fa-sliders text-[10px]"></i>
                    <span x-text="showMoreOptions ? '− Hide Notes & Order Status' : '+ Add Delivery Notes & Status'"></span>
                </button>

                <div x-show="showMoreOptions" x-cloak class="mt-3 grid grid-cols-1 gap-3.5 sm:grid-cols-12 p-3.5 rounded-xl bg-surface/50 border border-line/60 dark:border-strokedark dark:bg-boxdark2">
                    <div class="sm:col-span-4">
                        <label class="text-sm font-semibold text-muted block mb-1">Order Status</label>
                        <select name="order_status" class="ta-input !py-2 font-semibold">
                            <option value="new" @selected(!in_array(old('order_status', $order->order_status?->value ?? 'new'), ['delivered', 'cancelled', 'returned']))>Pending</option>
                            <option value="delivered" @selected(old('order_status', $order->order_status?->value ?? 'new') === 'delivered')>Delivered</option>
                            {{-- Cancelling is a permission of its own: Admins always have
                                 it, Managers only when an Admin grants it in Settings. --}}
                            @can('cancel-orders')
                                <option value="cancelled" @selected(in_array(old('order_status', $order->order_status?->value ?? 'new'), ['cancelled', 'returned']))>Cancelled</option>
                            @endcan
                        </select>
                    </div>

                    @if ($isEdit)
                        <div class="sm:col-span-4">
                            <label class="text-sm font-semibold text-muted block mb-1">Actual Delivery Date</label>
                            <input type="text" name="actual_delivery_date"
                                   x-datepicker="{ maxDate: 'today' }"
                                   value="{{ old('actual_delivery_date', optional($order->actual_delivery_date)->format('Y-m-d')) }}"
                                   class="ta-input !py-2 @error('actual_delivery_date') !border-danger @enderror">
                        </div>
                    @endif

                    <div class="{{ $isEdit ? 'sm:col-span-4' : 'sm:col-span-8' }}">
                        <label class="text-sm font-semibold text-muted block mb-1">Delivery Notes</label>
                        <input type="text" name="notes" maxlength="2000" class="ta-input !py-2"
                               placeholder="Gate code, instructions..."
                               value="{{ old('notes', $order->notes) }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- ========================================================================= --}}
        {{-- 2. Ordered Items Section --}}
        {{-- ========================================================================= --}}
        <div class="pt-6 border-t border-line/60 dark:border-strokedark space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-line/40 dark:border-strokedark">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand/10 text-brand text-xs font-bold">
                        <i class="fa-solid fa-couch"></i>
                    </span>
                    <h3 class="text-base font-bold text-ink dark:text-white">Order Items</h3>
                </div>
                <button type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface px-3 py-1.5 text-xs font-bold text-brand hover:bg-brand/10 transition dark:border-strokedark dark:bg-boxdark2"
                        x-on:click="addItem()">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Add Item</span>
                </button>
            </div>

            {{-- Column Headers for Desktop --}}
            <div class="hidden sm:grid sm:grid-cols-12 gap-3 text-xs font-bold uppercase tracking-wider text-muted px-3">
                <div class="col-span-5">Product</div>
                <div class="col-span-3">Colour</div>
                <div class="col-span-2 text-center">Qty</div>
                <div class="col-span-2 text-right">Price (€)</div>
            </div>

            <div class="space-y-3">
                <template x-for="(item, index) in items" :key="item.uid">
                    <div class="rounded-xl border border-line bg-surface/30 p-3.5 sm:p-3.5 transition hover:border-brand/40 dark:border-strokedark dark:bg-boxdark2">
                        
                        {{-- Mobile Item Title & Quick Actions --}}
                        <div class="flex items-center justify-between sm:hidden pb-2 mb-2.5 border-b border-line/40 dark:border-strokedark">
                            <span class="text-xs font-bold text-ink dark:text-white" x-text="`Item #${index + 1}`"></span>
                            <div class="flex items-center gap-2">
                                <button type="button" x-on:click="duplicateItem(index)"
                                        class="p-1 text-muted hover:text-brand transition"
                                        title="Duplicate item">
                                    <i class="fa-regular fa-copy text-xs"></i>
                                </button>
                                <template x-if="items.length > 1">
                                    <button type="button" x-on:click="removeItem(index)"
                                            class="p-1 text-muted hover:text-danger transition"
                                            title="Remove item">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </template>
                            </div>
                        </div>

                        {{-- Item Inputs Row --}}
                        <div class="grid grid-cols-12 gap-3 sm:items-center">

                            {{-- Product Name Autocomplete --}}
                            <div class="col-span-12 sm:col-span-5 relative">
                                <label class="text-xs font-semibold text-muted block sm:hidden mb-1">Product</label>
                                <input type="hidden" :name="`items[${index}][product_id]`" x-model="item.product_id">
                                
                                <div class="relative">
                                    <input type="text"
                                           class="ta-input !py-2 font-medium pr-7"
                                           :name="`items[${index}][item_name]`"
                                           x-model="item.item_name"
                                           placeholder="Type or select product..."
                                           autocomplete="off"
                                           x-on:focus="item.showProductMenu = true"
                                           x-on:input="onItemNameInput(item)"
                                           x-on:keydown.escape="item.showProductMenu = false">
                                    
                                    <button type="button"
                                            x-on:click="item.showProductMenu = !item.showProductMenu"
                                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted hover:text-ink">
                                        <i class="fa-solid fa-chevron-down text-xs transition duration-150"
                                           :class="{ 'rotate-180': item.showProductMenu }"></i>
                                    </button>
                                </div>

                                {{-- Product Dropdown Menu --}}
                                <div x-show="item.showProductMenu"
                                     x-cloak
                                     x-on:click.outside="item.showProductMenu = false"
                                     class="absolute z-50 mt-1 max-h-52 w-full overflow-y-auto rounded-xl border border-line bg-white p-1.5 shadow-xl dark:border-strokedark dark:bg-boxdark">
                                    
                                    <template x-for="p in filteredProducts(item.item_name)" :key="p.id">
                                        <button type="button"
                                                x-on:click="selectProduct(item, p)"
                                                class="flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-left text-sm transition hover:bg-brand/10 dark:hover:bg-boxdark2">
                                            <div>
                                                <div class="font-semibold text-ink dark:text-white" x-text="p.name"></div>
                                                <div class="text-xs text-muted">
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
                                                class="flex w-full items-center gap-1.5 rounded-lg bg-brand/5 p-1.5 font-semibold text-brand text-xs hover:bg-brand/10">
                                            <i class="fa-solid fa-sparkles text-xs"></i>
                                            <span>Use "<strong><span x-text="item.item_name"></span></strong>" as custom product</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Colour Combobox --}}
                            <div class="col-span-6 sm:col-span-3 relative">
                                <label class="text-xs font-semibold text-muted block sm:hidden mb-1">Colour</label>
                                <input type="hidden" :name="`items[${index}][colour_id]`" x-model="item.colour_id">

                                <div class="relative">
                                    <input type="text"
                                           class="ta-input !py-2 font-medium pl-8 pr-6"
                                           :name="`items[${index}][item_colour]`"
                                           x-model="item.item_colour"
                                           placeholder="Colour..."
                                           autocomplete="off"
                                           x-on:focus="item.showColourMenu = true"
                                           x-on:input="onColourInput(item)"
                                           x-on:keydown.escape="item.showColourMenu = false">

                                    <div class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2">
                                        <span class="block h-3.5 w-3.5 rounded-full border border-black/15 shadow-xs"
                                              :style="`background-color: ${currentColourHex(item)}`"></span>
                                    </div>

                                    <button type="button"
                                            x-on:click="item.showColourMenu = !item.showColourMenu"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 text-muted hover:text-ink">
                                        <i class="fa-solid fa-chevron-down text-xs transition duration-150"
                                           :class="{ 'rotate-180': item.showColourMenu }"></i>
                                    </button>
                                </div>

                                {{-- Colour Dropdown Menu --}}
                                <div x-show="item.showColourMenu"
                                     x-cloak
                                     x-on:click.outside="item.showColourMenu = false"
                                     class="absolute z-50 mt-1 max-h-48 w-full overflow-y-auto rounded-xl border border-line bg-white p-1.5 shadow-xl dark:border-strokedark dark:bg-boxdark">
                                    
                                    <template x-for="c in filteredColours(item)" :key="c.id">
                                        <button type="button"
                                                x-on:click="selectColour(item, c)"
                                                class="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-sm transition hover:bg-brand/10 dark:hover:bg-boxdark2">
                                            <span class="h-3.5 w-3.5 rounded-full border border-black/15"
                                                  :style="`background-color: ${c.hex || '#98A2B3'}`"></span>
                                            <span class="text-ink dark:text-white font-medium" x-text="c.name"></span>
                                        </button>
                                    </template>

                                    <div x-show="item.item_colour.trim() && !hasExactColourMatch(item)"
                                         class="border-t border-line/60 p-1.5 text-xs text-muted dark:border-strokedark">
                                        <button type="button"
                                                x-on:click="item.colour_id = ''; item.showColourMenu = false"
                                                class="flex w-full items-center gap-1.5 rounded-lg bg-brand/5 p-1.5 font-semibold text-brand text-xs hover:bg-brand/10">
                                            <i class="fa-solid fa-palette text-xs"></i>
                                            <span>Use "<strong><span x-text="item.item_colour"></span></strong>" as custom colour</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Quantity --}}
                            <div class="col-span-3 sm:col-span-2">
                                <label class="text-xs font-semibold text-muted block sm:hidden mb-1 text-center">Qty</label>
                                <input type="number" min="1" step="1" class="ta-input !py-2 font-bold text-center"
                                       placeholder="1"
                                       :name="`items[${index}][quantity]`" x-model.number="item.quantity">
                            </div>

                            {{-- Unit Price --}}
                            <div class="col-span-3 sm:col-span-2">
                                <label class="text-xs font-semibold text-muted block sm:hidden mb-1 text-right">Price (€)</label>
                                <input type="number" min="0" step="0.01" class="ta-input !py-2 font-bold text-right"
                                       placeholder="0.00"
                                       :name="`items[${index}][unit_price]`" x-model.number="item.unit_price">
                            </div>

                            <input type="hidden" :name="`items[${index}][discount]`" value="0">
                        </div>

                        {{-- Line Total & Note & Action Buttons Row --}}
                        <div class="mt-2.5 pt-2 border-t border-line/40 dark:border-strokedark flex items-center justify-between gap-3 text-xs">
                            <div class="flex-1">
                                <input type="text"
                                       class="w-full bg-transparent border-none p-0 text-muted placeholder:text-muted/60 text-xs focus:ring-0 focus:outline-none"
                                       :name="`items[${index}][notes]`"
                                       x-model="item.notes"
                                       placeholder="+ Add note (dimensions, custom details)...">
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="font-bold text-ink dark:text-white text-xs whitespace-nowrap">
                                    <span class="text-muted font-normal">Line Total: </span>
                                    <span class="text-brand font-black" x-text="money(lineTotal(item))"></span>
                                </div>

                                {{-- Desktop Action Tools --}}
                                <div class="hidden sm:flex items-center gap-1 pl-2 border-l border-line/60 dark:border-strokedark">
                                    <button type="button" x-on:click="duplicateItem(index)"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-muted hover:text-brand hover:bg-surface transition dark:hover:bg-boxdark"
                                            title="Duplicate item">
                                        <i class="fa-regular fa-copy text-xs"></i>
                                    </button>
                                    <template x-if="items.length > 1">
                                        <button type="button" x-on:click="removeItem(index)"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-muted hover:text-danger hover:bg-surface transition dark:hover:bg-boxdark"
                                                title="Remove item">
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ========================================================================= --}}
        {{-- 3. Grand Total & Action Footer Bar --}}
        {{-- ========================================================================= --}}
        <div class="pt-6 border-t border-line dark:border-strokedark flex flex-col sm:flex-row items-center justify-between gap-4">
            {{-- Grand Total --}}
            <div class="flex items-baseline gap-2">
                <span class="text-xs font-bold uppercase tracking-wider text-muted">Total Order Amount:</span>
                <span class="text-3xl font-black text-brand tracking-tight" x-text="money(grandTotal)"></span>
            </div>

            {{-- Buttons --}}
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <a href="{{ $isEdit ? route('orders.show', $order) : route('orders.index') }}"
                   class="btn btn-light px-5 py-2.5 text-sm font-semibold flex-1 sm:flex-initial text-center">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary px-8 py-2.5 text-sm font-bold shadow-sm flex-1 sm:flex-initial">
                    <i class="fa-solid fa-check mr-1.5"></i> {{ $isEdit ? 'Save Order' : 'Create Order' }}
                </button>
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
                    if (this.$refs.deliveryDate._flatpickr) {
                        this.$refs.deliveryDate._flatpickr.setDate(iso, true);
                    } else {
                        this.$refs.deliveryDate.value = iso;
                    }
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
                if (this.$refs.customerPhoneAlt) this.$refs.customerPhoneAlt.value = '';
                if (this.$refs.customerEmail) this.$refs.customerEmail.value = '';
                if (this.$refs.customerAddress) this.$refs.customerAddress.value = '';
                if (this.$refs.customerState) this.$refs.customerState.value = '';
                if (this.$refs.customerZip) this.$refs.customerZip.value = '';
            },

            lookupController: null,

            async checkDuplicate(phone) {
                const clean = (phone || '').replace(/\D/g, '');
                if (clean.length < 5) {
                    this.duplicate = null;
                    return;
                }

                if (this.lookupController) {
                    this.lookupController.abort();
                }
                this.lookupController = new AbortController();

                try {
                    const response = await fetch(`${config.lookupUrl}?phone=${encodeURIComponent(phone.trim())}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        signal: this.lookupController.signal,
                    });
                    const data = await response.json();
                    if (data && data.found) {
                        this.duplicate = data;
                        this.customerId = data.customer.id;

                        // Autofill customer inputs
                        if (this.$refs.customerName && (!this.$refs.customerName.value || this.$refs.customerName.value === data.customer.name)) {
                            this.$refs.customerName.value = data.customer.name || '';
                        }
                        if (this.$refs.customerPhoneAlt && (!this.$refs.customerPhoneAlt.value || this.$refs.customerPhoneAlt.value === data.customer.phone_alt)) {
                            this.$refs.customerPhoneAlt.value = data.customer.phone_alt || '';
                        }
                        if (this.$refs.customerEmail && (!this.$refs.customerEmail.value || this.$refs.customerEmail.value === data.customer.email)) {
                            this.$refs.customerEmail.value = data.customer.email || '';
                        }
                        if (this.$refs.customerAddress && (!this.$refs.customerAddress.value || this.$refs.customerAddress.value === data.customer.address)) {
                            this.$refs.customerAddress.value = data.customer.address || '';
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
                    if (e.name !== 'AbortError') {
                        this.duplicate = null;
                    }
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
