<h1>Cacheable PHP page</h1>

<p>This page is cacheable, and should be made into a static page.</p>

<p>Random: <?php echo random_int(0, PHP_INT_MAX); ?></p>

<?php
$this->page()->setDynamic(false);
