<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN"
	"http://www.w3.org/TR/html4/strict.dtd">

<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>Plagiabot</title>
</head>

<body>
<div id="header">
	<h1>
		<center>Plagiabot</center>
	</h1>
	<ul class="nav global nav-pills nav-justified">
		<li class="active"><a data-toggle="pill" href="#tab1">Unchecked</a></li>
		<li><a data-toggle="pill" href="#tab2">Recent changes</a></li>
		<li><a data-toggle="pill" href="#tab3">True positives</a></li>
	</ul>

	<div class="tab-content">
		<div id="tab1" class="tab-pane fade in active">
			<p>Dummy text here</p>
		</div>
		<div id="tab2" class="tab-pane fade">
			<h3>Menu 1</h3>
			<p>Some content in menu 1.</p>
		</div>
		<div id="tab3" class="tab-pane fade">
			<table class="table table-striped table-hover padded">
				<?= $html ?>
			</table>
		</div>
	</div>
</div>
</body>