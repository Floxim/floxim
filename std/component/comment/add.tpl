<div fx:template="add" fx:of="add" class="comment_add">
    {css}add.css{/css}
    <form method="post" action="">
    	<label for="user_name">
    		<span>User Name</span>
    		<input type="text" name="user_name">
    	</label>
    	<label for="comment_text">
    		<span>Comment</span>
    		<textarea name="comment_text"></textarea> 
    	</label>
    	<input type="submit" name="addcomment" value="Add">
    </form>
</div>