function addNewDictionary(){
    var f = document.getElementById("file").files[0]; 

    if (f) {
      var r = new FileReader();
      r.onload = function(e) { 
	      var contents = e.target.result;
              var nameField=document.getElementById("name");
              var name=nameField.value==""?f.name:nameField.value;
              $.post(gdn.url('plugin/dictionary/upload'),{ name: name, content: contents }, function (data) {
        alert(data);
    });
      }
      r.readAsText(f);
    } else { 
      alert("Please select valid file!");
    }
    /**/
}
function removeDictionary(id,name)
{
    if(confirm("Are you sure you want to delete dictionary '"+name+"'?")){
        $.post(gdn.url('plugin/dictionary/remove'),{ DictionaryID: id}, function (data) {
        location.reload();
        }
        );
    }
}
