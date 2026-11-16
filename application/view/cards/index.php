<h1>Finance Cards</h1>
<?php if (Session::userIsLoggedIn()) { ?>
	<div class='new-list-item-container'>
		<span class='new-list-item-button'>
			<a href='/cards/add'>New Card</a>
		</span>
	</div>
<?php } ?>

<!-- echo out the system feedback (error and success messages) -->
<?php $this->renderFeedbackMessages(); ?>

<?php if ($this->cards) { ?>
    <?php foreach($this->cards as $key => $value) { ?>
        <p class="list-item">
            <span href='' class="list-item-title"><?= $value->name ?>
            <span class="list-item-footer">Provider: <?= $value->provider ?></span>
        </p>
    <?php } ?>
<?php } else { ?>
    <div>No cards found.</div>
<?php } ?>
