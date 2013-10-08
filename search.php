<?php
    require_once 'includes/constants.php';
    require_once 'includes/config.php';

    $user = new User();
    if(!$user->loggedIn()){
        redirect('index.php');
    }

    // Connect to transaction database
    mysql_connect(DB_SERVER, DB_USER, DB_PASSWORD) or die(mysql_error());
    mysql_select_db(DB_NAME) or die(mysql_error());

    $PARS = Array(     
      //'pg' => (isset($_GET['pg']))?$_GET['pg']:1,     //page number  
        'ts' => (isset($_GET['ts']))?$_GET['ts']:0,     //Transaction offset   
        'tn' => (isset($_GET['ts']))?$_GET['tn']:20,    //transactions / page 
        'oc' => (isset($_GET['oc']))?$_GET['oc']:1,     //Order-by column
        'od' => (isset($_GET['od']))?$_GET['od']:0,     //Order direction
        'kw' => (isset($_GET['kw']))?$_GET['kw']:"",    //Keywords
        'fd' => (isset($_GET['fd']))?$_GET['fd']:Null,  //From date
        'td' => (isset($_GET['td']))?$_GET['td']:Null,  //To date
        'st' => (isset($_GET['st']))?$_GET['st']:"0",   //Status
    );
    
    $COLS = Array(           
        Array('TransactionID', function($row){return $row['TransactionID'];}),
        Array('TransactionDate', function($row){return $row['TransactionDate'];}),
        Array('Description', function($row){return $row['Description'];}),
        Array('Status', function($row){return $row['Status'];}),
        Array('Amount', function($row){return $row['Amount'] / 100;}),
        Array('', function($row){return "<a id='edit' href='transaction.php?hid=".$row['HistoryID']."'>Edit</a>";}),
    );
    
    $ORDS = Array(
        Array('Descending', 'DESC'),
        Array('Ascending', 'ASC'),
    );
?>


<!DOCTYPE html>
<html>
    <head>
        <title>Transaction History</title>
        <meta charset="utf-8"/>
        <!--[if lt IE 9]>
            <script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
        <![endif]

        <link rel="stylesheet" type="text/css" href="/css/style2.css">
        <link rel="stylesheet" type="text/css" href="/css/styling.css">-->
        <script src="/js/expander.js"></script>
    </head>

    <body id="main">
        <div id="box">
        
        <h1>Transaction History</h1>
        <a href="index.php?logout=1" class="btn btn-default">Logout</a>
        <?php
            if ($user->isAdmin()) {
                echo "<a href='admin.php' class='btn btn-info'>Admin</a>";
            }
        ?>            

        <form method="get" action="search.php" class="content">
            <div class="bordered">
                <h2>Search</h2>
                <input type="submit" name="search" value="Update">
                


                <table id="options-basic">
                    <tr>
                        <td>Keywords</td>
                        <td>From date</td>
                        <td>To date</td>
                    </tr>
                    <tr>
                        <td><input type='text' name='kw' value='<?php echo $PARS['kw']?>'></td>
                        <td><input type='date' name='fd' <?php echo (($PARS['fd']!=Null)?"value=".$PARS['fd']:"")?>></td>
                        <td><input type='date' name='td' <?php echo (($PARS['fd']!=Null)?"value=".$PARS['fd']:"")?>></td>
                    </tr>
                </table>
                <table id="options-advanced">
                    <tr>
                        <td>Status</td>
                        <td>Order by</td>
                        <td>Order direction</td>
                        <td>Transactions per page</td>
                    </tr>
                    <tr>
                        <td>
                            <select name="st">
                                <option value=0>-- Select --</option>
                                <?php
                                    $statuses = mysql_query("SELECT * FROM Status") or die(mysql_error());
                                    while($row = mysql_fetch_array($statuses)){
                                        $sel = ($row['ID']==$st)?"selected":"";
                                        echo "<option value=".$row['ID']." ".$sel." >".$row['Name']."</option>";
                                    }
                                ?>
                            </select>
                        </td>
                        <td>
                            <select name="oc">
                                <?php
                                    foreach($COLS as $k => $v){
                                        $sel = ($k==$PARS['oc'])?"selected":"";
                                        echo "<option value=".$k." ".$sel." >".$v[0]."</option>";
                                    }
                                ?>
                            </select>
                        </td>
                        <td>
                            <select name='od'>
                                <?php
                                    foreach($ORDS as $k => $v){
                                        $sel = ($k==$PARS['od'])?"selected":"";
                                        echo "<option value=".$k." ".$sel." >".$v[0]."</option>";
                                    }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <a id="search-expander">Show advanced options</a>
                <a id="search-hider" style="display:none">Hide advanced options</a>
            </div><!-- end bordered-->

            <?php
                //select the transactions to display on the page
                $whr = "";
                if ($PARS['st'] != 0) {
                    $whr = $whr . "StatusID = ".$st."\nAND ";
                }
                if ($PARS['fd'] != Null AND $PARS['td'] != Null) {
                    $whr = $whr . "TransactionDate BETWEEN ".$fd." AND ".$td."\nAND ";
                }
                if ($PARS['kw'] != "") {
                    $whr = $whr . "History.Description LIKE '%".$kw."%' OR Comment LIKE '%".$kw."%'\nAND ";
                }
                if (substr($whr,-4) == "AND ") {
                    $whr = substr($whr,0,strlen($whr)-4);
                }
                if ($whr != "") {
                    $whr = "WHERE " . $whr;
                }

                $search="
                    SELECT
                        History.ID AS HistoryID,
                        History.TransactionID AS TransactionID,
                        DATE(History.TransactionDate) AS TransactionDate,
                        History.Description AS Description,
                        History.Amount AS Amount,
                        Status.Name AS Status,
                        Status.ID AS StatusID
                    FROM (
                        SELECT TransactionID, Max(ModificationDate) AS ModificationDate
                        FROM History
                        GROUP BY TransactionID
                    ) AS Latest
                    INNER JOIN History ON Latest.TransactionID = History.TransactionID
                    AND Latest.ModificationDate = History.ModificationDate
                    INNER JOIN Status ON Status.ID = History.StatusID ".$whr;
                
                $result = mysql_query("SELECT COUNT(*) AS Count FROM (".$search.") AS T") or die(mysql_error());;
                $count = mysql_fetch_array($result)['Count'];
                $PARS['ts'] = max(0, min($count-$PARS['tn'],$PARS['ts']));
                $post = "ORDER BY ".$COLS[$PARS['oc']][0]." ".$ORDS[$PARS['od']][1].
                        " LIMIT ".$PARS['tn']." OFFSET ".$PARS['ts'].";";
                $page = mysql_query($search.$post) or die(mysql_error());
                
            ?>

            <table id="transaction-list" summary = "List of Transactions">
                <thead>
                    <?php
                        foreach($COLS as $v){
                            echo "<td>".$v[0]."</td>";
                        }
                    ?>
                </thead>
                <tbody>
                <?php
                    while($row = mysql_fetch_array($page))
                    {
                        echo "<tr>";
                        foreach($COLS as $v){
                            echo "<td>".$v[1]($row)."</td>";
                        }
                        echo "</tr>";
                    }
                ?>
                </tbody>
            </table>
            <input type='hidden' name='ts' value=<?php echo $PARS['ts']?>>;

            <div id="pagination">
                <input type="submit" name="frst" value="First">
                <input type="submit" name="prev" value="Previous">
                <?php
                    echo "Displaying transactions ".$PARS['ts']." to ".($PARS['ts']+$PARS['tn'])." of ".$count
                ?>
                <input type="submit" name="next" value="Next">
                <input type="submit" name="last" value="Last">
            </div><!-- end pagination -->
        </form>
    </div><!-- end box -->
    <div id="sidebar">
        <?php include_once("sidebar.php")?>
    </div>
    </body>
</html>
