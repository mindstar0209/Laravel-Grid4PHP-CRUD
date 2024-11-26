<x-app-layout :title=$title>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <main class="mt-6">
                {!! $grid !!}
            </main>
        </div>
    </div>

    <!-- Modal -->
    <div id="select-modal"
        class="fixed inset-0 z-50 flex justify-center items-center w-full h-full bg-[#00000059] bg-opercity-50"
        x-data="{ show: {{ session('show_welcome_modal', false) ? 'true' : 'false' }} }" x-show="show" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-90" x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-90" style="display: none; backdrop-filter: blur(3px)" tabindex="-1"
        aria-hidden="true" data-modal-target="select-modal">
        <div class="relative p-4 w-full max-w-md max-h-full">
            <!-- Modal content -->
            <div class="relative bg-white rounded-lg shadow dark:bg-gray-700">
                <!-- Modal header -->
                <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t dark:border-gray-600">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Select Menu
                    </h3>
                    <button type="button"
                        class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm h-8 w-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white"
                        @click="show = false" data-modal-toggle="select-modal">
                        <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                        </svg>
                        <span class="sr-only">Close modal</span>
                    </button>
                </div>
                <!-- Modal body -->
                <div class="p-4 md:p-5">
                    <p class="text-gray-500 dark:text-gray-400 mb-4">Select your desired menu:</p>
                    <ul class="space-y-4 mb-4">
                        <li>
                            <input type="radio" id="job-1" name="job" value="job-1" class="hidden peer"
                                required />
                            <label for="job-1"
                                class="inline-flex items-center justify-between w-full p-5 text-gray-900 bg-white border border-gray-200 rounded-lg cursor-pointer dark:hover:text-gray-300 dark:border-gray-500 dark:peer-checked:text-blue-500 peer-checked:border-blue-600 peer-checked:text-blue-600 hover:text-gray-900 hover:bg-gray-100 dark:text-white dark:bg-gray-600 dark:hover:bg-gray-500">
                                <div class="block">
                                    <div class="w-full text-lg font-semibold">Reports</div>
                                    {{-- <div class="w-full text-gray-500 dark:text-gray-400">Flowbite</div> --}}
                                </div>
                                <svg class="w-4 h-4 ms-3 rtl:rotate-180 text-gray-500 dark:text-gray-400"
                                    aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 14 10">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                        stroke-width="2" d="M1 5h12m0 0L9 1m4 4L9 9" />
                                </svg>
                            </label>
                        </li>
                        <li>
                            <input type="radio" id="job-2" name="job" value="job-2" class="hidden peer">
                            <label for="job-2"
                                class="inline-flex items-center justify-between w-full p-5 text-gray-900 bg-white border border-gray-200 rounded-lg cursor-pointer dark:hover:text-gray-300 dark:border-gray-500 dark:peer-checked:text-blue-500 peer-checked:border-blue-600 peer-checked:text-blue-600 hover:text-gray-900 hover:bg-gray-100 dark:text-white dark:bg-gray-600 dark:hover:bg-gray-500">
                                <div class="block">
                                    <div class="w-full text-lg font-semibold">Assign Reports</div>
                                    {{-- <div class="w-full text-gray-500 dark:text-gray-400">Alphabet</div> --}}
                                </div>
                                <svg class="w-4 h-4 ms-3 rtl:rotate-180 text-gray-500 dark:text-gray-400"
                                    aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 14 10">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                        stroke-width="2" d="M1 5h12m0 0L9 1m4 4L9 9" />
                                </svg>
                            </label>
                        </li>
                        <li>
                            <input type="radio" id="job-3" name="job" value="job-3" class="hidden peer">
                            <label for="job-3"
                                class="inline-flex items-center justify-between w-full p-5 text-gray-900 bg-white border border-gray-200 rounded-lg cursor-pointer dark:hover:text-gray-300 dark:border-gray-500 dark:peer-checked:text-blue-500 peer-checked:border-blue-600 peer-checked:text-blue-600 hover:text-gray-900 hover:bg-gray-100 dark:text-white dark:bg-gray-600 dark:hover:bg-gray-500">
                                <div class="block">
                                    <div class="w-full text-lg font-semibold">System Admin</div>
                                    {{-- <div class="w-full text-gray-500 dark:text-gray-400">Apple</div> --}}
                                </div>
                                <svg class="w-4 h-4 ms-3 rtl:rotate-180 text-gray-500 dark:text-gray-400"
                                    aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 14 10">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                        stroke-width="2" d="M1 5h12m0 0L9 1m4 4L9 9" />
                                </svg>
                            </label>
                        </li>
                    </ul>
                    <button
                        class="text-white inline-flex w-full justify-center bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        Next step
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<!-- Included Static CSS/JS Files -->
{{-- <link rel="stylesheet" type="text/css" media="screen"
    href="{{ asset('assets/gridphp/themes/base/jquery-ui.custom.css') }}"> --}}
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="stylesheet" type="text/css" media="screen" href="{{ asset('assets/gridphp/jqgrid/css/ui.jqgrid.bs.css') }}">
{{-- <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/redmond/jquery-ui.css"> --}}
<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/cupertino/jquery-ui.css">
</link>
<link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/jquery-ui-multiselect-widget/1.17/jquery.multiselect.css">
<link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/jquery-ui-multiselect-widget/1.17/jquery.multiselect.filter.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<script src="{{ asset('assets/gridphp/jquery.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/gridphp/jqgrid/js/i18n/grid.locale-en.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/gridphp/jqgrid/js/jquery.jqGrid.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/gridphp/themes/jquery-ui.custom.min.js') }}" type="text/javascript"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-ui-multiselect-widget/1.17/jquery.multiselect.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-ui-multiselect-widget/1.17/jquery.multiselect.filter.js">
</script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    .ui-multiselect-menu {
        font-size: 12px;
    }

    .ui-widget {
        font-size: 12px;
    }

    .ui-state-highlight,
    .ui-widget-content .ui-state-highlight {
        background: #2e6e9e;
        color: white;
        border: 1px solid #2e6e9e;
    }

    .ui-state-highlight a,
    .ui-widget-content .ui-state-highlight a {
        color: white;
    }

    .ui-alt-rows {
        background: #f3f9ff url(images/ui-bg_glass_75_d0e5f5_1x400.png) 50% 50% repeat-x;
    }

    .ui-datepicker,
    .ui-datepicker select.ui-datepicker-month,
    .ui-datepicker select.ui-datepicker-year {
        width: max-content;
    }

    .ui-datepicker-title [type=text],
    [type=email],
    [type=url],
    [type=password],
    [type=number],
    [type=date],
    [type=datetime-local],
    [type=month],
    [type=search],
    [type=tel],
    [type=time],
    [type=week],
    [multiple],
    textarea,
    select {
        padding: 0 8px;
    }

    .ui-datepicker .ui-datepicker-prev,
    .ui-datepicker .ui-datepicker-next {
        top: 4px;
    }

    .FormData .CaptionTD {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        height: 38px;
        width: 100%;
    }

    .ui-multiselect-menu ul li label [type=checkbox]:focus,
    [type=radio]:focus {
        --tw-ring-color: none;
        --tw-ring-shadow: none;
    }
</style>
<!-- CSRF Token for Ajax calls -->
<script type="text/javascript">
    jQuery.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content')
        }
    });
</script>
