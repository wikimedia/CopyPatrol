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
	<div class="alert alert-success">
		<strong>Hello!</strong> This is a web-version of Plagiabot. Database writes for this tool are disabled at the
		moment.
		Feel free to play around and log your complaints, feature requests and appreciations
		<a href="https://en.wikipedia.org/wiki/User_talk:EranBot/Copyright/rc">here</a>.
	</div>
	<ul class="nav global nav-pills nav-justified">
		<li class="active"><a data-toggle="pill" href="#tab1">Recent changes</a></li>
		<li><a data-toggle="pill" href="#tab2">True positives</a></li>
		<li><a data-toggle="pill" href="#tab3">False positives</a></li>
	</ul>

	<div class="tab-content">
		<div id="tab1" class="tab-pane fade in active">
			<!--			<table class="table table-bordered table-hover padded">-->
			<?= $html ?>
			<!--			</table>-->
		</div>
		<div id="tab2" class="tab-pane fade">
			<h3>Coming soon</h3>
		</div>
		<div id="tab3" class="tab-pane fade">
			<h3>Coming soon</h3>
		</div>
	</div>
</div>
</body>
