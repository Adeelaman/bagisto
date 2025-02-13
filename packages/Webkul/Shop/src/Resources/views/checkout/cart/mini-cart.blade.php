<!-- Mini Cart Vue Component -->
<v-mini-cart>
    <span
        class="icon-cart text-2xl cursor-pointer"
        role="button"
        aria-label="@lang('shop::app.checkout.cart.mini-cart.shopping-cart')"
    ></span>
</v-mini-cart>

@pushOnce('scripts')
    <script type="text/x-template" id="v-mini-cart-template">
        <x-shop::drawer>
            <!-- Drawer Toggler -->
            <x-slot:toggle>
                <span class="relative">
                    <span
                        class="icon-cart text-2xl cursor-pointer"
                        role="button"
                        aria-label="@lang('shop::app.checkout.cart.mini-cart.shopping-cart')"
                        tabindex="0"
                    ></span>

                    <span
                        class="absolute px-2 -top-4 ltr:left-5 rtl:right-5 py-1.5 bg-[#060C3B] rounded-[44px] text-white text-xs font-semibold leading-[9px]"
                        v-if="cart?.items_qty"
                    >
                        @{{ cart.items_qty }}
                    </span>
                </span>
            </x-slot:toggle>

            <!-- Drawer Header -->
            <x-slot:header>
                <div class="flex justify-between items-center">
                    <p class="text-2xl font-medium">
                        @lang('shop::app.checkout.cart.mini-cart.shopping-cart')
                    </p>
                </div>

                <p class="text-base">
                    @lang('shop::app.checkout.cart.mini-cart.offer-on-orders')
                </p>
            </x-slot:header>

            <!-- Drawer Content -->
            <x-slot:content>
                <!-- Cart Item Listing -->
                <div 
                    class="grid gap-12 mt-9" 
                    v-if="cart?.items?.length"
                >
                    <div 
                        class="flex gap-x-5" 
                        v-for="item in cart?.items"
                    >
                        <!-- Cart Item Image -->
                        <div class="">
                            <a :href="`{{ route('shop.product_or_category.index', '') }}/${item.product_url_key}`">
                                <img
                                    :src="item.base_image.small_image_url"
                                    class="max-w-[110px] max-h-[110px] rounded-xl"
                                />
                            </a>
                        </div>

                        <!-- Cart Item Information -->
                        <div class="grid flex-1 gap-y-2.5 place-content-start justify-stretch">
                            <div class="flex flex-wrap justify-between">
                                <a  class="max-w-4/5" :href="`{{ route('shop.product_or_category.index', '') }}/${item.product_url_key}`">
                                    <p
                                        class="text-base font-medium"
                                        v-text="item.name"
                                    >
                                    </p>
                                </a>

                                <p
                                    class="text-lg"
                                    v-text="item.formatted_price"
                                >
                                </p>
                            </div>

                            <!-- Cart Item Options Container -->
                            <div
                                class="grid gap-x-2.5 gap-y-1.5 select-none"
                                v-if="item.options.length"
                            >
                                <!-- Details Toggler -->
                                <div class="">
                                    <p
                                        class="flex gap-x-[15px] items-center text-base cursor-pointer"
                                        @click="item.option_show = ! item.option_show"
                                    >
                                        @lang('shop::app.checkout.cart.mini-cart.see-details')

                                        <span
                                            class="text-2xl"
                                            :class="{'icon-arrow-up': item.option_show, 'icon-arrow-down': ! item.option_show}"
                                        ></span>
                                    </p>
                                </div>

                                <!-- Option Details -->
                                <div class="grid gap-2" v-show="item.option_show">
                                    <div class="" v-for="option in item.options">
                                        <p class="text-sm font-medium">
                                            @{{ option.attribute_name + ':' }}
                                        </p>

                                        <p class="text-sm">
                                            @{{ option.option_label }}
                                        </p>
                                    </div>

                                </div>
                            </div>

                            <div class="flex gap-5 items-center flex-wrap">

                                <!-- Cart Item Quantity Changer -->
                                <x-shop::quantity-changer
                                    name="quantity"
                                    ::value="item?.quantity"
                                    class="gap-x-2.5 max-w-[150px] max-h-9 py-1.5 px-3.5 rounded-[54px]"
                                    @change="updateItem($event, item)"
                                >
                                </x-shop::quantity-changer>

                                <!-- Cart Item Remove Button -->
                                <button
                                    type="button"
                                    class="text-[#0A49A7]"
                                    @click="removeItem(item.id)"
                                >
                                    @lang('shop::app.checkout.cart.mini-cart.remove')
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty Cart Section -->
                <div
                    class="pb-8"
                    v-else
                >
                    <div class="grid gap-y-5 b-0 place-items-center">
                        <img src="{{ bagisto_asset('images/thank-you.png') }}">

                        <p class="text-xl">
                            @lang('shop::app.checkout.cart.mini-cart.empty-cart')
                        </p>
                    </div>
                </div>
            </x-slot:content>

            <!-- Drawer Footer -->
            <x-slot:footer>
                <div v-if="cart?.items?.length">
                    <div class="flex justify-between items-center mt-8 mb-8 px-6 pb-2 border-b border-[#E9E9E9]">
                        <p class="text-sm font-medium text-[#6E6E6E]">
                            @lang('shop::app.checkout.cart.mini-cart.subtotal')
                        </p>

                        <p
                            v-if="! isLoading"
                            class="text-3xl font-semibold"
                            v-text="cart.formatted_grand_total"
                        >
                        </p>
                        
                        <div
                            v-else
                            class="flex justify-center items-center"
                        >
                            <!-- Spinner -->
                            <svg
                                class="absolute animate-spin  h-8 w-8  text-[5px] font-semibold text-blue"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                aria-hidden="true"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                ></circle>
                
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                ></path>
                            </svg>
                
                            <span class="opacity-0 realative text-3xl font-semibold" v-text="cart.formatted_grand_total"></span>
                        </div>
                    </div>

                    <!-- Cart Action Container -->
                    <div class="grid gap-2.5 px-6">
                        <a
                            href="{{ route('shop.checkout.onepage.index') }}"
                            class="block w-full mx-auto py-4 px-11 bg-navyBlue rounded-2xl text-white text-base font-medium text-center cursor-pointer max-sm:px-5"
                        >
                            @lang('shop::app.checkout.cart.mini-cart.continue-to-checkout')
                        </a>

                        <div class="block text-base text-center font-medium cursor-pointer">
                            <a href="{{ route('shop.checkout.cart.index') }}">
                                @lang('shop::app.checkout.cart.mini-cart.view-cart')
                            </a>
                        </div>
                    </div>
                </div>
            </x-slot:footer>
        </x-shop::drawer>
    </script>

    <script type="module">
        app.component("v-mini-cart", {
            template: '#v-mini-cart-template',

            data() {
                return  {
                    cart: null,

                    isLoading:false,
                }
            },

           mounted() {
                this.getCart();

                /**
                 * To Do: Implement this.
                 *
                 * Action.
                 */
                this.$emitter.on('update-mini-cart', (cart) => {
                    this.cart = cart;
                });
           },

           methods: {
                getCart() {
                    this.$axios.get('{{ route('shop.api.checkout.cart.index') }}')
                        .then(response => {
                            this.cart = response.data.data;
                        })
                        .catch(error => {});
                },

                updateItem(quantity, item) {
                    this.isLoading = true;

                    let qty = {};

                    qty[item.id] = quantity;

                    this.$axios.put('{{ route('shop.api.checkout.cart.update') }}', { qty })
                        .then(response => {
                            if (response.data.message) {
                                this.cart = response.data.data;
                            } else {
                                this.$emitter.emit('add-flash', { type: 'warning', message: response.data.data.message });
                            }

                            this.isLoading = false;
                        }).catch(error => this.isLoading = false);
                },

                removeItem(itemId) {
                    this.$axios.post('{{ route('shop.api.checkout.cart.destroy') }}', {
                            '_method': 'DELETE',
                            'cart_item_id': itemId,
                        })
                        .then(response => {
                            this.cart = response.data.data;

                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        })
                        .catch(error => {});
                },
            }
        });
    </script>
@endpushOnce
