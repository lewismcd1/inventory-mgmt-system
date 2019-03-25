<?php
require_once "../database/db.php";
class Manage
{

    public function manageRecord($table){
        global $con;
        if ($table == "categories")
            $sql = "SELECT p.cid,p.category_name as category, c.category_name as parent, p.status FROM categories p LEFT JOIN categories c ON p.parent_cat=c.cid ";
        else if($table == "products")
            $sql = "SELECT p.pid, p.product_name,c.category_name,b.brand_name,p.product_price,p.product_stock,p.added_date,p.barcode,p.p_status 
              FROM products p,brands b,categories c WHERE p.bid = b.bid AND p.cid = c.cid";
        else
            $sql = "SELECT * FROM $table";

        return $con->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteRecord($table, $pk, $id){
        global $con;
        if($table == "categories"){
            $pre_stmt = $con->prepare("SELECT $id FROM categories WHERE parent_cat = ?");
            $pre_stmt->execute(array($id));
            $result = $pre_stmt->fetch();
            if ($result)
                return "DEPENDANT_CATEGORY";
            else {
                $pre_stmt = $con->prepare("DELETE FROM $table WHERE $pk = ?");
                return $pre_stmt->execute(array($id)) ? "CATEGORY_DELETED" : 0;
            }
        } else {
            $pre_stmt = $con->prepare("DELETE FROM $table WHERE $pk = ? LIMIT 1");
            return $pre_stmt->execute(array($id)) ? "DELETED" : 0;
        }
    }

    public function getSingleRecord($table, $pk, $id)
    {
        global $con;
        $pre_stmt = $con->prepare("SELECT * FROM $table WHERE $pk = ? LIMIT 1");
        $pre_stmt->execute(array($id));
        return $pre_stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateRecord($table, $where, $fields){
        global $con;
        $sql = "";
        $condition = "";
        foreach ($where as $key => $value) {
            // id = '5' AND m_name = 'something'
            $condition .= $key . "=" . $con->quote($value) . " AND ";
        }
        $condition = substr($condition, 0, -5);
        foreach ($fields as $key => $value) {
            //UPDATE table SET m_name = '' , qty = '' WHERE id = '';
            $sql .= $key . "=" . $con->quote($value) . ", ";
        }
        $sql = substr($sql, 0, -2);
        $sql = "UPDATE " . $table . " SET " . $sql . " WHERE " . $condition;
        return $con->query($sql) ? "UPDATED" : 0;
    }

       public function storeInvoice($orderdate, $custname, $ar_tqty, $ar_qty, $ar_price, $ar_proname, $sub_total, $tax, $net_total, $paid, $due){
        global $con;
        $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $con->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        try{
        $pre_stmt = $con->prepare("INSERT INTO `invoice`(`customer_name`, `order_date`, `sub_total`, `tax`, `net_total`, `paid`, `due`) VALUES (?,?,?,?,?,?,?)");
        $pre_stmt->execute(array($custname, $orderdate, $sub_total, $tax, $net_total, $paid, $due));
        $invoice_no = $con->lastInsertId();
        }
        catch(Exception $e){
            var_dump($e);
            return;
        }
        if ($invoice_no != null) {
            //loop until end of array
            for ($i = 0; $i < count($ar_price); $i++) {
                // Finding the remaining quantity after order
                $rem_qty = $ar_tqty[$i] - $ar_qty[$i];
                if ($rem_qty < 0)
                    return "QTY_NOT_AVAIL";
                if ($paid != $net_total)
                    return "PAID_ERROR";
                 else
                     //Update product stock
                    $con->prepare("UPDATE products SET product_stock = ? WHERE product_name = ?")->execute(array($rem_qty,$ar_proname[$i]));
                $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $con->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                $tempar = array($invoice_no, $ar_proname[$i], $ar_price[$i], $ar_qty[$i]);
                try{
                $insert_product = $con->prepare("INSERT INTO `invoice_details`(`invoice_no`, `product_name`, `price`, `qty`) VALUES (?,?,?,?)");
                //$ar_proname[$i] = "123";
                $insert_product->execute(array($invoice_no, $ar_proname[$i], $ar_price[$i], $ar_qty[$i]));
                }
                catch(Exception $e){
                    //var_dump($e);
                    var_dump($tempar);
                //}
                }
            }
        }
        return $invoice_no;
    }
    /* public function manageRecords($table,$pno){
         $a = $this->pagination($this->con,$table,$pno,5);
         if ($table == "categories"){
             $sql = "SELECT p.cid,p.category_name as category, c.category_name as parent, p.status FROM categories p LEFT JOIN categories c ON p.parent_cat=c.cid ".$a["limit"];
         }else if($table == "products"){
             $sql = "SELECT p.pid, p.product_name,c.category_name,b.brand_name,p.product_price,p.product_stock,p.added_date,p.p_status
               FROM products p,brands b,categories c WHERE p.bid = b.bid AND p.cid = c.cid ".$a["limit"];
             }
         else{
             $sql = "SELECT * FROM ".$table." ".$a["limit"];
         }
         $result = $this->con->query($sql) or die($this->con->error);
         $rows = array();
         if ($result->num_rows>0){
             while ($row = $result->fetch_assoc()){
                 $rows[] = $row;
             }
         }
         return ["rows"=>$rows, "pagination"=>$a["pagination"]];
     }*/

    /*function resetPassword($email){
    $sql = "SELECT id, notes FROM user WHERE email = '$email' LIMIT 1";
        $query = mysqli_query($this->con,$sql);
        if(mysqli_num_rows($query) == 1){
            $row = mysqli_fetch_array($query);
            $uid = $row["id"];
            $notes = $row["notes"];
            //Send email to check if valid email
            $to = $email;
            $subj = "Reset Password";
            $msg = "Click link to reset password<br/>";
            $msg .= "http://localhost/inventory-management/public_html/reset-password.php?uid=".$uid."&email=".$email;
            $header = "From : Admin@Inventory-Management\r\n";
            $header .= "Reply-To: admin@inventory-management.com\r\n";
            $header .= "Return-Path: lewis-inventory-management.com\r\n";
            if($notes != ""){
                echo "Please check your inbox, an email has already been sent to reset your password.";
                exit();
            }else{

                if (mail($to,$subj,$msg, $header)){
                            echo "Please confirm your email to reset your password";
                            exit();
                        }
    }


        }
}*/


    /*private function pagination($con, $table, $pno, $n)
     {
         $query = $con->query("SELECT COUNT(*) as rows FROM " . $table);
         $row = mysqli_fetch_assoc($query);
         $pageno = $pno;
         $numberOfRecordsPerPage = $n;

         $last = ceil($row["rows"] / $numberOfRecordsPerPage);
         $pagination = "<ul class='pagination'>";

         if ($last != 1) {
             if ($pageno > 1) {
                 $previous = "";
                 $previous = $pageno - 1;
                 $pagination .= "<li class='page-item'><a class='page-link' pn='".$previous."' href='#' style='color:#333;'> Previous </a></li></li>";
             }
             for($i=$pageno - 5;$i< $pageno ;$i++){
                 if ($i > 0) {
                     $pagination .= "<li class='page-item'><a class='page-link' pn='".$i."' href='#'> ".$i." </a></li>";
                 }

             }
             $pagination .= "<li class='page-item'><a class='page-link' pn='".$pageno."' href='#' style='color:#333;'> $pageno </a></li>";
             for ($i=$pageno + 1; $i <= $last; $i++) {
                 $pagination .= "<li class='page-item'><a class='page-link' pn='".$i."' href='#'> ".$i." </a></li>";
                 if ($i > $pageno + 4) {
                     break;
                 }
             }
             if ($last > $pageno) {
                 $next = $pageno + 1;
                 $pagination .= "<li class='page-item'><a class='page-link' pn='".$next."' href='#' style='color:#333;'> Next </a></li></ul>";
             }
         }
         $limit = "LIMIT " . ($pageno - 1) * $numberOfRecordsPerPage . "," . $numberOfRecordsPerPage;
         return ["pagination" => $pagination, "limit" => $limit];
     }*/
}

?>