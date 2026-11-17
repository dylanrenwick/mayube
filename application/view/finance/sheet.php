<h3>Transactions — <?= $this->encodeHTML($this->month_title); ?></h3>

<?php $this->renderFeedbackMessages(); ?>

<form id="month-form" method="get" action="<?= $this->encodeHTML(Config::get('URL') . 'finance/sheets'); ?>">
	<label for="transaction-month">Month</label>
	<input id="transaction-month" type="month" name="month" value="<?= $this->encodeHTML($this->month); ?>" min="0001-01" max="9999-12" required />
	<button type="submit">Show Month</button>
</form>

<details>
	<summary>New transaction</summary>
	<form method="post" action="<?= $this->encodeHTML(Config::get('URL') . 'finance/createTransaction'); ?>">
		<input type="hidden" name="csrf_token" value="<?= $this->encodeHTML(Csrf::makeToken()); ?>" />
		<input type="hidden" name="month" value="<?= $this->encodeHTML($this->month); ?>" />
		<label for="new-transaction-date">Date</label>
		<input id="new-transaction-date" type="date" name="timestamp" value="<?= $this->encodeHTML($this->default_date); ?>" required />
		<label for="new-transaction-name">Name</label>
		<input id="new-transaction-name" type="text" name="title" required />
		<label for="new-transaction-amount">Amount (CAD)</label>
		<input id="new-transaction-amount" type="number" name="amount" step=".01" required />
		<label for="new-transaction-type">Transaction type</label>
		<select id="new-transaction-type" name="category" required>
			<option value="">Select a type</option>
			<?php foreach ($this->categories as $category) { ?>
				<option value="<?= (int) $category->id; ?>"><?= $this->encodeHTML($category->name); ?></option>
			<?php } ?>
		</select>
		<button type="submit" <?= empty($this->categories) ? 'disabled' : ''; ?>>Add Transaction</button>
	</form>
</details>

<?php $total = array_reduce($this->transactions, fn ($sum, $transaction) => $sum + (float) $transaction->amount, 0.0); ?>
<p>Total: $<?= number_format($total, 2); ?> CAD</p>

<style>
	.monthly-transactions { overflow-x: auto; }
	.monthly-transactions table { width: 100%; border-collapse: collapse; }
	.monthly-transactions th, .monthly-transactions td { padding: .75rem; text-align: left; border-bottom: 1px solid #888; }
	.monthly-transactions .transaction-amount { text-align: right; white-space: nowrap; }
	.monthly-transactions [hidden] { display: none; }
	.monthly-transactions input, .monthly-transactions select { min-width: 8rem; }
	#month-form, details { margin-bottom: 1rem; }
</style>

<?php if (empty($this->transactions)) { ?>
	<p>No transactions for this month.</p>
<?php } else { ?>
	<div class="monthly-transactions">
		<table>
			<thead>
				<tr>
					<th scope="col">Date</th>
					<th scope="col">Transaction</th>
					<th scope="col" class="transaction-amount">Amount (CAD)</th>
					<th scope="col">Card</th>
					<th scope="col">Transaction type</th>
					<th scope="col">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($this->transactions as $transaction) { ?>
					<?php $formId = 'transaction-edit-' . (int) $transaction->id; ?>
					<tr data-transaction-row>
						<td>
							<span data-display><?= $this->encodeHTML(substr($transaction->date, 0, 10)); ?></span>
							<input data-edit hidden form="<?= $formId; ?>" type="date" name="timestamp" aria-label="Transaction date" value="<?= $this->encodeHTML(substr($transaction->date, 0, 10)); ?>" required />
						</td>
						<td>
							<span data-display><?= $this->encodeHTML($transaction->description); ?></span>
							<input data-edit hidden form="<?= $formId; ?>" type="text" name="title" aria-label="Transaction name" value="<?= $this->encodeHTML($transaction->description); ?>" required />
						</td>
						<td class="transaction-amount">
							<span data-display>$<?= number_format((float) $transaction->amount, 2); ?></span>
							<input data-edit hidden form="<?= $formId; ?>" type="number" name="amount" step="any" aria-label="Amount in CAD" value="<?= $this->encodeHTML((string) $transaction->amount); ?>" required />
						</td>
						<td><?= $this->encodeHTML($transaction->card_name ?? '—'); ?></td>
						<td>
							<span data-display><?= $this->encodeHTML($transaction->category_name); ?></span>
							<select data-edit hidden form="<?= $formId; ?>" name="category" aria-label="Transaction type" required>
								<?php foreach ($this->categories as $category) { ?>
									<option value="<?= (int) $category->id; ?>" <?= (int) $category->id === (int) $transaction->category_id ? 'selected' : ''; ?>><?= $this->encodeHTML($category->name); ?></option>
								<?php } ?>
							</select>
						</td>
						<td>
							<button type="button" data-display data-open-editor>Edit</button>
							<form data-edit hidden id="<?= $formId; ?>" class="transaction-edit-form" method="post" action="<?= $this->encodeHTML(Config::get('URL') . 'finance/updateTransaction'); ?>">
								<input type="hidden" name="csrf_token" value="<?= $this->encodeHTML(Csrf::makeToken()); ?>" />
								<input type="hidden" name="transaction_id" value="<?= (int) $transaction->id; ?>" />
								<button type="submit">Save</button>
								<button type="button" data-cancel-editor>Cancel</button>
							</form>
							<span class="transaction-status" role="status" aria-live="polite"></span>
						</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
<?php } ?>

<script>
	(() => {
		const month = document.getElementById('transaction-month');
		month.addEventListener('change', () => {
			if (month.checkValidity()) document.getElementById('month-form').requestSubmit();
		});

		document.querySelectorAll('[data-transaction-row]').forEach((row) => {
			const form = row.querySelector('.transaction-edit-form');
			const status = row.querySelector('.transaction-status');
			let saving = false;
			function toggleEditor(editing) {
				row.querySelectorAll('[data-display]').forEach((element) => { element.hidden = editing; });
				row.querySelectorAll('[data-edit]').forEach((element) => { element.hidden = !editing; });
				status.textContent = '';
			}
			row.querySelector('[data-open-editor]').addEventListener('click', () => toggleEditor(true));
			row.querySelector('[data-cancel-editor]').addEventListener('click', () => {
				form.reset();
				toggleEditor(false);
			});
			form.addEventListener('submit', async (event) => {
				event.preventDefault();
				if (saving) return;
				const body = new FormData(form);
				const buttons = [...form.querySelectorAll('button')];
				buttons.forEach((button) => { button.disabled = true; });
				saving = true;
				status.textContent = 'Saving…';
				try {
					const response = await fetch(form.action, {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Accept': 'application/json' },
						body,
					});
					const result = await response.json().catch(() => null);
					if (!response.ok || !result?.success) {
						throw new Error(result?.error || 'Unable to save this transaction. Please try again.');
					}
					window.location.reload();
				} catch (error) {
					status.textContent = error.message || 'Unable to save this transaction. Please try again.';
				} finally {
					saving = false;
					buttons.forEach((button) => { button.disabled = false; });
				}
			});
		});
	})();
</script>
