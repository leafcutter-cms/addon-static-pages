<h1>Cacheable PHP page with GET parameters</h1>

<p>This page allows GET parameters, and is cacheable. Its parameters will be encoded to create a unique URL.</p>

<p>Random: <?php echo random_int(0, PHP_INT_MAX); ?></p>

<p><code>foo</code> GET parameter: <?php echo htmlentities($this->param('foo')); ?></p>

<ul>
    <li><a href="?">no parameters</a></li>
    <li><a href="?foo=bar">foo=bar</a></li>
</ul>

<?php
if ($this->param('foo')) {
    $this->page()->setParent('?');
    $this->page()->meta('name','With foo = '.$this->param('foo'));
}
$this->page()->setDynamic(false);
