<?php
declare(strict_types=1);

/**
 * Collects a payment against an existing invoice using the shared payment and
 * account-ledger tables. Draft invoices remain drafts until explicitly confirmed.
 *
 * @return array<string,mixed>
 */
function vk_invoice_collect_payment(PDO $pdo, array $input, int $actorId): array
{
    $invoiceId = (int) ($input['invoice_id'] ?? 0);
    $rawAmount = trim((string) ($input['amount'] ?? ''));
    $method = strtolower(trim((string) ($input['method'] ?? '')));
    $paidDate = trim((string) ($input['payment_date'] ?? ''));
    $referenceNumber = trim((string) ($input['reference_number'] ?? ''));
    $note = trim((string) ($input['note'] ?? ''));
    $requestToken = trim((string) ($input['request_token'] ?? ''));

    if ($invoiceId <= 0) throw new InvalidArgumentException('Invalid invoice.');
    if ($rawAmount === '' || !is_numeric($rawAmount) || !is_finite((float) $rawAmount) || (float) $rawAmount <= 0) {
        throw new InvalidArgumentException('Enter a valid payment amount greater than zero.');
    }
    $amountCents = (int) round((float) $rawAmount * 100);
    if ($amountCents <= 0) throw new InvalidArgumentException('Payment must be at least LKR 0.01.');
    if (!in_array($method, ['cash', 'card', 'bank', 'online'], true)) {
        throw new InvalidArgumentException('Choose a supported payment method.');
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $paidDate);
    if (!$date || $date->format('Y-m-d') !== $paidDate) {
        throw new InvalidArgumentException('Choose a valid payment date.');
    }
    if (mb_strlen($referenceNumber) > 128) throw new InvalidArgumentException('Reference number is too long.');
    if (mb_strlen($note) > 255) throw new InvalidArgumentException('Payment notes cannot exceed 255 characters.');
    if (preg_match('/^[a-f0-9]{48}$/', $requestToken) !== 1) {
        throw new InvalidArgumentException('Payment form expired. Refresh the invoice and try again.');
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $invoiceSt = $pdo->prepare(
            'SELECT id, invoice_number, customer_id, grand_total, paid_amount, status, is_draft
             FROM invoices WHERE id = ? FOR UPDATE'
        );
        $invoiceSt->execute([$invoiceId]);
        $invoice = $invoiceSt->fetch(PDO::FETCH_ASSOC);
        if (!$invoice) throw new RuntimeException('Invoice not found.');
        if (($invoice['status'] ?? '') === 'cancelled') throw new InvalidArgumentException('Cancelled invoices cannot receive payments.');

        $existingSt = $pdo->prepare('SELECT id, invoice_id, amount, method, paid_at, reference_number, note, received_by FROM payments WHERE request_token = ? LIMIT 1');
        $existingSt->execute([$requestToken]);
        $existing = $existingSt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($existing) {
            if ((int) $existing['invoice_id'] !== $invoiceId) throw new InvalidArgumentException('This payment request was already used for another invoice.');
            $summary = vk_invoice_payment_snapshot($invoice);
            $payment = vk_invoice_payment_details($pdo, $existing);
            if ($ownsTransaction) $pdo->commit();
            return ['duplicate' => true, 'payment' => $payment] + $summary;
        }

        $grandCents = max(0, (int) round((float) $invoice['grand_total'] * 100));
        $alreadyPaidCents = max(0, (int) round((float) $invoice['paid_amount'] * 100));
        $balanceCents = max(0, $grandCents - $alreadyPaidCents);
        if ($amountCents > $balanceCents) {
            throw new RuntimeException('Payment exceeds the current outstanding balance of ' . formatCurrency($balanceCents / 100) . '. Refresh and enter an amount within the balance.');
        }

        $accountSt = $pdo->prepare('SELECT id FROM accounts WHERE customer_id = ? LIMIT 1');
        $accountSt->execute([(int) $invoice['customer_id']]);
        $customerAccountId = (int) $accountSt->fetchColumn();
        if ($customerAccountId <= 0) throw new RuntimeException('Customer account not found.');
        $systemAccountId = system_account_id($pdo);
        $paidAt = $date->format('Y-m-d') . ' ' . date('H:i:s');

        $insert = $pdo->prepare(
            'INSERT INTO payments
                (invoice_id, customer_id, customer_account_id, amount, method, paid_at, note, reference_number, received_by, request_token)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $insert->execute([
            $invoiceId,
            (int) $invoice['customer_id'],
            $customerAccountId,
            $amountCents / 100,
            $method,
            $paidAt,
            $note !== '' ? $note : null,
            $referenceNumber !== '' ? $referenceNumber : null,
            $actorId > 0 ? $actorId : null,
            $requestToken,
        ]);
        $paymentId = (int) $pdo->lastInsertId();

        $newPaidCents = $alreadyPaidCents + $amountCents;
        $newPaid = $newPaidCents / 100;
        $isDraft = !empty($invoice['is_draft']) || ($invoice['status'] ?? '') === 'draft';
        if ($isDraft) {
            $pdo->prepare('UPDATE invoices SET paid_amount = ? WHERE id = ?')->execute([$newPaid, $invoiceId]);
            $status = 'draft';
        } else {
            $status = $newPaidCents >= $grandCents ? 'paid' : 'partial';
            $pdo->prepare('UPDATE invoices SET paid_amount = ?, status = ? WHERE id = ?')->execute([$newPaid, $status, $invoiceId]);
        }

        // Match the existing payment route's accounting behavior exactly once.
        ledger_apply($pdo, $customerAccountId, 0, $amountCents / 100,
            'Payment for invoice ' . $invoice['invoice_number'] . ' (' . $method . ')', null, $paymentId, null);
        ledger_apply($pdo, $systemAccountId, $amountCents / 100, 0,
            'Receipt — invoice ' . $invoice['invoice_number'] . ' (' . $method . ')', null, $paymentId, null);

        $updatedInvoice = $invoice;
        $updatedInvoice['paid_amount'] = $newPaid;
        $updatedInvoice['status'] = $status;
        $updatedInvoice['is_draft'] = $isDraft ? 1 : 0;
        $paymentSt = $pdo->prepare('SELECT id, invoice_id, amount, method, paid_at, reference_number, note, received_by FROM payments WHERE id = ?');
        $paymentSt->execute([$paymentId]);
        $payment = vk_invoice_payment_details($pdo, $paymentSt->fetch(PDO::FETCH_ASSOC) ?: []);
        $summary = vk_invoice_payment_snapshot($updatedInvoice);

        if ($ownsTransaction) $pdo->commit();
        return ['duplicate' => false, 'payment' => $payment] + $summary;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** @return array<string,mixed> */
function vk_invoice_payment_details(PDO $pdo, array $payment): array
{
    $userSt = $pdo->prepare('SELECT fullname, username FROM users WHERE id = ? LIMIT 1');
    $userSt->execute([(int) ($payment['received_by'] ?? 0)]);
    $user = $userSt->fetch(PDO::FETCH_ASSOC) ?: [];
    $id = (int) ($payment['id'] ?? 0);
    return [
        'id' => $id,
        'reference' => 'PAY-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT),
        'payment_date' => (string) ($payment['paid_at'] ?? ''),
        'method' => (string) ($payment['method'] ?? ''),
        'amount' => round((float) ($payment['amount'] ?? 0), 2),
        'transaction_reference' => (string) ($payment['reference_number'] ?? ''),
        'note' => (string) ($payment['note'] ?? ''),
        'received_by' => trim((string) ($user['fullname'] ?? '')) ?: (string) ($user['username'] ?? '—'),
    ];
}

/** @return array{paid_amount:float,balance:float,status:string,is_draft:bool} */
function vk_invoice_payment_snapshot(array $invoice): array
{
    $grandCents = max(0, (int) round((float) ($invoice['grand_total'] ?? 0) * 100));
    $paidCents = max(0, (int) round((float) ($invoice['paid_amount'] ?? 0) * 100));
    $isDraft = !empty($invoice['is_draft']) || ($invoice['status'] ?? '') === 'draft';
    return [
        'paid_amount' => $paidCents / 100,
        'balance' => max(0, $grandCents - $paidCents) / 100,
        'status' => $isDraft ? 'draft' : ($paidCents <= 0 ? 'unpaid' : ($paidCents >= $grandCents ? 'paid' : 'partial')),
        'is_draft' => $isDraft,
    ];
}
