$(document).ready(function(){
	(function(){
		var bar=$('#progress .bar');
		var percent=$('#progress .percent');
		var status=$('#status');
		$('#formProgress').ajaxForm({
			beforeSend:function(){
				status.empty();
				var percentVal='0%';
				bar.width(percentVal)
				percent.html(percentVal);
			},
			uploadProgress:function(event,position,total,percentComplete){
				var percentVal=percentComplete+'%';
				bar.width(percentVal)
				percent.html(percentVal);
				if(percentComplete==100)
				{
					status.html('<div class="loader"></div><p>Décompression du fichier zip</p>');
				}
			},
			complete:function(xhr){
				bar.width('100%');
				percent.html('100%');
				status.html(xhr.responseText);
			}
		});
	})();
});