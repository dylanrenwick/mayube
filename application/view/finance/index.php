<h3>Finance</h3>

<?php $this->renderFeedbackMessages(); ?>

<nav aria-label="Finance">
	<a class="list-item" href="<?= $this->encodeHTML(Config::get('URL') . 'cards'); ?>">Manage Cards</a>
	<a class="list-item" href="<?= $this->encodeHTML(Config::get('URL') . 'finance/queue'); ?>">Transaction Queue</a>
	<a class="list-item" href="<?= $this->encodeHTML(Config::get('URL') . 'finance/sheets'); ?>">Sheets</a>
</nav>
