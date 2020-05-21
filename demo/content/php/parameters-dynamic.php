<h1>Dynamic PHP page with GET parameters</h1>

<p>This page allows GET parameters, and is dynamic. It may not work exactly as expected with static pages, as queries are encoded into filenames.</p>

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