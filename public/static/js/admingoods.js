/**
 * 获取多级联动的商品分类
 */
function get_category(id,next,select_id){
    $.ajax({
        type : "GET",
        url  : '/index.php?m=Home&c=api&a=get_category&parent_id='+ id,
        dataType:'json',
        success: function(data) {
            var html = "<option value='0'>请选择商品分类</option>";
            if(data.status == 1){
                for (var i=0 ;i<data.result.length;i++){
                    html+= "<option value='"+data.result[i].id+"'>"+data.result[i].name+"</option>";
                }
            }
            $('#'+next).empty().html(html);
            (select_id > 0) && $('#'+next).val(select_id);//默认选中
        }
    });
}