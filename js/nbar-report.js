/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
$(function(){$("#nbarSetupLink").on("click",function(e){e.preventDefault();var guide=document.getElementById("nbarSetupGuide");if(!guide.open)guide.showModal();});});
$(function(){$("#nbar_filter [name=mode]").on("change",function(){$("#nbar_filter [name=app],#nbar_filter [name=internal],#nbar_filter [name=external]").remove();});});

$(function(){
 function refreshTimespan(){
  var v=$("#nbar_period").val();
  if(v!=="0")$.getJSON("flowview.php",{action:"gettimespan",report:"nbar",predefined_timespan:v},function(d){
   $("#nbar_date1").val(d.current_value_date1);$("#nbar_date2").val(d.current_value_date2);
  });
 }
 function flowReturn(report){
  var u=new URL($("#nbar_return").attr("href"),location.href);
  if(report)u.searchParams.set("report",report);
  u.searchParams.set("device_id",$("#nbar_filter [name=listener]").val());
  u.searchParams.set("predefined_timespan",$("#nbar_period").val()==="1000"?"0":$("#nbar_period").val());
  u.searchParams.set("date1",$("#nbar_date1").val());u.searchParams.set("date2",$("#nbar_date2").val());
  location.href=u.href;
 }
 $("#nbar_report_select").on("change",function(){if(this.value!=="nbar")flowReturn(this.value);});
 $("#nbar_return").on("click",function(e){e.preventDefault();flowReturn();});
 $("#nbar_period").on("change",refreshTimespan);
 $("#nbar_date1,#nbar_date2").on("change",function(){
  $("#nbar_period").val("0");if(theme!=="classic")$("#nbar_period").selectmenu("refresh");
 });
});

