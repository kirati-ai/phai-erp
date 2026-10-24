<?php

/** Short description of each page on the overview cards (System\Flow) — key = the menu's mnu_link */
return [
    // Purchasing
    '/purchase-requisitions' => 'Departments ask purchasing to buy what they need, or create from MRP suggestions; no commitment to a vendor yet',
    '/purchase-orders'       => 'Order from the vendor: price, quantity and delivery date; print and send to the vendor',
    '/receipts'              => 'Receive goods against a purchase order; stock goes up and goods received not invoiced is posted',
    '/landed-costs'          => 'Freight, customs clearing and duty on imported goods, allocated into item cost',
    '/purchase-returns'      => 'Return damaged or off-spec goods to the vendor, then deduct on the payable or wait for a credit note',
    '/stock?type=RM'         => 'Raw materials on hand by warehouse, location and lot',
    '/vendor-rating'         => 'Grade vendors on on-time delivery and returned goods',

    // Inventory
    '/stock'          => 'Quantity and cost on hand by warehouse, location and lot; transfer and change status',
    '/stock/history'  => 'Every stock movement (receipts, issues, transfers, adjustments), traceable line by line',
    '/stock-counts'   => 'Print count tags, enter the counted quantities, then adjust stock to match',
    '/stock-card'     => 'Stock and materials report under section 87(3) for the Revenue Department',

    // Production
    '/item-planning' => 'Planning settings of each item at each site: safety stock, reorder point, order quantity',
    '/mrp'           => 'Works out what to buy or make, how much and when, from sales and stock',
    '/work-orders'   => 'Order production, issue materials, record labor, receive finished goods into stock',
    '/qc'            => 'Quality inspection results of production: good, defective, inspector',

    // Sales
    '/quotations'     => 'Quote prices to customers; once won, turn the quotation into a sales order',
    '/sales-orders'   => 'Customer orders: items, prices, delivery dates, credit check',
    '/shipments'      => 'Ship against sales orders; stock goes out at cost of goods sold',
    '/invoices'       => 'Invoices / tax invoices from shipments; sets up receivables and output VAT',
    '/billings'       => 'Group invoices of the same customer into a billing note to collect payment',
    '/sales-returns'  => 'Take goods back from customers; stock goes up, then issue a credit note',
    '/stock?type=FG'  => 'Finished goods on hand, ready to sell',

    // Accounting — payables
    '/payables'       => 'Record vendor invoices; creates payables and input VAT',
    '/ap-vouchers'    => 'Group the payables of one vendor for payment approval',
    '/vouchers'       => 'Pay against payment vouchers, deducting withholding tax',
    '/wht'            => 'Withholding tax certificates (50 Tawi) for vendors',
    '/vat-purchases'  => 'Monthly input VAT report for filing PP 30',
    '/wht-report'     => 'PND 3 / 53 monthly withholding tax returns',

    // Accounting — receivables
    '/deposits'           => 'Take deposits against sales orders, issue a tax invoice, deduct them when invoicing',
    '/ar-invoices'        => 'Manual invoices, credit notes and debit notes',
    '/etax'               => 'Email electronic tax invoices to customers and the Revenue Department',
    '/receivables'        => 'Outstanding receivables by customer and age',
    '/cash-receipts'      => 'Receive customer payments, clear receivables, issue receipts',
    '/journals?status=AR' => 'Accounting entries that come from sales',
    '/vat-sales'          => 'Monthly output VAT report for filing PP 30',

    // Accounting — general ledger
    '/journals'             => 'Every journal entry, and manual adjusting entries',
    '/bank-rec'             => 'Match bank transactions in the system against the bank statement',
    '/vat-return'           => 'File PP 30 monthly: output VAT less input VAT',
    '/trial-balance'        => 'Balance of every account, and the monthly period close',
    '/financial-statements' => 'Balance sheet and income statement',

    // Accounting — fixed assets
    '/fixed-assets' => 'Asset register: register assets and post monthly depreciation',
];
