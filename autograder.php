<?php
/*
Mary Machado
CS490 - 101
Middle Part - Release Version
*/

$info = file_get_contents('php://input');
$infoDecoded = json_decode($info, true);

$action = $infoDecoded["action"];
$url = 'http://afsaccess2.njit.edu/~jsr56/backEndRelease.php';

switch($action) {
  case 'autograde':             #professor wants to grade all the tests
    autograde($url);            #grade the tests
  break;

  default:                      #send everything to the back
    sendBack($url, $info);
}

function sendBack($url, $info){
  $ch= curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
  curl_setopt($ch, CURLOPT_POSTFIELDS,$info );
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  $result = curl_exec($ch);
	curl_close($ch);
  echo $result;
}

function autograde($url){
  $info = array('action' => 'getAs');

  $ch= curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($info));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  $result = curl_exec($ch);
  curl_close($ch);


  $info = $result;
  $infoDecoded = json_decode($result, true);#making it into an array
  #echo "this is from the back:\n"; var_dump($info);
  $tests = $infoDecoded['responses'];#this var holds the students tests
  $answerKey =  $infoDecoded['correctAnswers'];#this var holds the answer key
  #echo "This is the answerKey:\n"; var_dump($answerKey);
  $answerKeySize = count($answerKey);
  $testCount = count($tests);#how many tests I have to grade
  $totalQs = count($answerKey); #holds how many questions the test has

  $toSend = ['action' => 'gradeT', 'stuGrades' => []];  #array that will have the students correct answers to send to db

  $testFile = "testFile.py";  #this is the file where all the code will be written to

  /************** Right here I will check for parameters **************/
  $i = 0;
  foreach ($tests as $testCount) {#here I am looping each test
  	$stuID = $tests[$i]['student'];#getting student ID
    $theArr = array('student' => $stuID);
    array_push($toSend['stuGrades'], $theArr);

    $toSend['stuGrades'][$i]['answers']= []; #making an empty array to hold each student tests answers
    $answers = $tests[$i]['answers']; #this holds the answers of current test

    $j=0;
    foreach ($answers as $totalQs) {#here I am looping each answer from the current test
      $answer = $answers[$j]; #here I am holding the answer # $j from test # $i
      $wrote = $answer['stuAnswer']; #this is the function student wrote in the test
      #echo "student answer: "; var_dump($wrote);
      $qnum = $answers[$j]['qnum'];#getting qnum from each answer
      #echo "Test # ".$i . " Answer #".$j ." qnum = " .$qnum;

      /************** Grab the maxPoint to deduct if wrong **************/
      $maxPts = $answerKey[$j]['maxpts'];
      $otherQuestions = 0;

      /************** Check the function name **************/
      $fName = $answerKey[$j]['fname'];
      $subString = strtok($wrote, '(');  #getting a string where includes def and function name
      $stuFname = substr($subString, 4);  #getting rid off def'\s'

      $funcFound = strstr($wrote, $fName);
      if ($funcFound == true){
        $funcFoundPts = 0;
        $otherQuestions += 5;
      }
      else{
        $funcFoundPts = 5;
        $otherQuestions += 5;
      }

      /************** Check for ":" **************/
      $colonFound = preg_match('/(def)\s\w+\(\w((,)?\w+)*\)\:/', $wrote); #grep looking for the first instance of :
      if ($colonFound == true){ #if found no point deductions
        $colonFoundPts = 0;
        $otherQuestions += 3;
      }
      else{ #otehrwise 3 pts taken away
        $colonFoundPts = 3;
        $otherQuestions += 3;
      }

      /************** Check for the constraint **************/
      $constraint = $answerKey[$j]['constraint'];
      $constraintFound = strstr($wrote, $constraint); #string search for the given topic

      if ($constraintFound == true){ #if found no point deductions
        $constraintFoundPts = 0;
        $otherQuestions += 3;
      }
      else{
        $constraintFoundPts = 3; #otherwise 3 pts taken away
        $otherQuestions += 3;
      }

      $toWrite = $tests[$i]['answers'][$j]['stuAnswer'];  #student answer to be written in the file
      #echo "this is what will be writen in file";  var_dump($toWrite);
      $testCases = $answerKey[$j]['tests'];  #this contains the args and the ans
      #var_dump($testCases);
      $countCases = count($testCases);
      $totalCases = count($testCases);

      $arguments = [];
      $n = 0;
      foreach ($testCases as $countCases) { #looping inside each question to grab testcases and correct answers testcases
        $case = $testCases[$n];

      /***** now I wanna know on each test case, how many arguments I will have *****/
        $args = $case['args']; #this holds all the args in each test case
        $argument = $case['args'];
        $argsCount = count($args); #this holds how many args I have in each testcase

        $caseAnsw = $case['ans'];
        $qtid = $case['qtid'];

        $argsArray = [];

        $p = 0;
        foreach ($args as $argsCount) {
          #echo "p is: " .$p ." and the arg I'm pushing is: " .$argsCount;
          array_push($argsArray, $args[$p]);        #arguments
          $p++;
        }
        $argsString = json_encode($argsArray);
        $argsString = str_replace('[', '', $argsString);
        $argsString = str_replace(']', '', $argsString);
        $argsString = str_replace('"', '', $argsString);

        #echo "this is the testcase: " .$argsString ."\n"; #############################################

                /******** write in the file *********/
        #echo "2b written in the file: "; $wrote2 = json_encode($wrote); var_dump($wrote);

        $open_write = fopen($testFile, 'w'); #handle to open and write in the file
        if ($colonFound == true){
          fwrite($open_write, $wrote);  #handle to write the code
        }
        else{
          $position1 = intval(strpos($wrote, '('));
          $position2 = intval(strpos($wrote, ')'));
          $endOfArgumento = $position2 - $position1;
          $argumentos = substr($wrote, $position1+1 ,$endOfArgumento-1);
          $restOfFunc = substr($wrote, $position2+1);
          fwrite($open_write, 'def ' .$stuFname ."(" .$argumentos ."):" ."\n" ."\t" .$restOfFunc);  #writting the function
        }

        $lookForPrint = strstr($wrote, 'print'); #string search for print

        /************* Calling the function *************/
        # If "print" is in the function, no need to add it, otherwise I have to add it so I can capture an output when
        # executing the python file

        if($lookForPrint == true)
          fwrite($open_write, "\n" .$stuFname ."(" .$argsString .")");
        else
            fwrite($open_write, "\n" ."print(" .$stuFname ."(" .$argsString ."))");

        fclose($open_write);    #let's close the file

        exec("python ./testFile.py >| compare.txt"); # here I am saving the output from the file in a txt file
        $fileOutput = file_get_contents ( './compare.txt');
        $fileOutput = trim($fileOutput);

        #echo "this is the file output: "; var_dump($fileOutput);

        $casePoints = ($maxPts - $otherQuestions) / intval($totalCases);   # points left for all testCases
        $pCasePoints =$casePoints / intval($totalCases);   #this is the value for each testcase

        #echo "\nthis is the answer from db: "; var_dump($caseAnsw);
        if ($fileOutput == $caseAnsw){
          $fileOutputPts = 0;
          $pts = 0 ;
        }
        else{
          $fileOutputPts = $casePoints;
          $pts = $casePoints ;
          $ptsForCases += $pCasePoints;
        }

      $argument = json_encode($argument);
      $anArray = array ('qtid' => $qtid, 'pts' => $pts, 'file_out' => $fileOutput);
      array_push($arguments, $anArray);

      $n++;
      }

      $ptsLost = $funcFound + $colonFound + $constraintFound + $ptsForCases;
      $maxPts = $maxPts - $ptsLost;

      /************** Making an array that will be pushed into the toSend array **************/
      $answerInf = array('qnum'        => $qnum,
                        'fName'        => $funcFoundPts,
                        'colon'        => $colonFoundPts,
                        'constraint'   => $constraintFoundPts,
                        'casePoints'   => $pts,
                        'qPoints'      => $maxPts,
                        'testCases'    => []
                  );

      array_push($toSend['stuGrades'][$i]['answers'], $answerInf);
      $toSend['stuGrades'][$i]['answers'][$j]['testCases'] = $arguments;

      $j++; # go to next question
  	}
  	$i++; # go to next test
	}
  $js= json_encode($toSend);
  #var_dump($js);
  sendBack($url, $js); # all tests graded are sent to db
}
?>
