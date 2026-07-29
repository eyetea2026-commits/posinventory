<?php

namespace App\Notifications\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

trait FormatsReturnItemsTable
{
    // Raw HTML, not a MailMessage ->line() markdown table — MailMessage has
    // no built-in table() method, and a hand-rolled HTML table renders more
    // predictably across email clients than relying on CommonMark's pipe-table
    // syntax surviving the markdown-to-HTML pass intact.
    private function buildItemsTableHtml(Collection $items): HtmlString
    {
        $rows = $items->map(function ($item) {
            $name = e($item->product?->ProductName ?? 'Unknown product');
            $qty = e((string) $item->Quantity);
            $reason = e($item->Reason ?? '—');

            return "<tr><td style=\"padding:8px 0;border-bottom:1px solid #e4e4e7;font-size:14px;\">{$name}</td><td style=\"padding:8px 0;border-bottom:1px solid #e4e4e7;font-size:14px;text-align:center;\">{$qty}</td><td style=\"padding:8px 0;border-bottom:1px solid #e4e4e7;font-size:14px;\">{$reason}</td></tr>";
        })->implode('');

        return new HtmlString(
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border-collapse:collapse;">'
            . '<tr><th style="text-align:left;padding-bottom:8px;border-bottom:1px solid #e4e4e7;font-size:13px;color:#71717a;">Product</th>'
            . '<th style="text-align:center;padding-bottom:8px;border-bottom:1px solid #e4e4e7;font-size:13px;color:#71717a;">Qty</th>'
            . '<th style="text-align:left;padding-bottom:8px;border-bottom:1px solid #e4e4e7;font-size:13px;color:#71717a;">Reason</th></tr>'
            . $rows
            . '</table>'
        );
    }
}
