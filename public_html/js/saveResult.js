function saveState( id, str ) {
	str = 'id=' + id + '&value=' + str
	$.ajax({
		type: "POST",
		dataType: "text",
		url: "response.php",
		data: str,
		success: function( ret ) {
			alert("Form submitted successfully.\nReturned: " + ret );
		}
	});
}
