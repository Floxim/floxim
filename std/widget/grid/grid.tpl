<table fx:template="two_columns_table" fx:of="widget_grid.two_columns" class="std_two_columns_table std_grid">
    {css}std_grid.less{/css}
    <tr>
        <td fx:each="$areas" class="table-grid-{$keyword}" fx:area="$id" fx:area-name="$name"></td>
    </tr>
</table>
        