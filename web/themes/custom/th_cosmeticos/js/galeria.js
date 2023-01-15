jQuery(document).ready(function ($) {
 console.log('prueba');
 $('.mini_img').click(function(e){
  
  var url_img = $(this).attr( "src" );
  $('#expandedImg').attr( "src", url_img);
 $('#expandedImg').parent().css('display','block');
 


  //console.log(expandImg);
 });

});


// function myFunction(imgs) {
//   // Get the expanded image
//   var expandImg = document.getElementById("expandedImg");
//   // Get the image text
//   var imgText = document.getElementById("imgtext");
//   // Use the same src in the expanded image as the image being clicked on from the grid
//   expandImg.src = imgs.src;
//   // Use the value of the alt attribute of the clickable image as text inside the expanded image
//   imgText.innerHTML = imgs.alt;
//   // Show the container element (hidden with CSS)
//   expandImg.parentElement.style.display = "block";
// }