<?php

namespace mazpaijo\attachmentsAws2\controllers;

use mazpaijo\attachmentsAws2\models\File;
use mazpaijo\attachmentsAws2\models\UploadForm;
use mazpaijo\attachmentsAws2\ModuleTrait;
use Yii;
use yii\helpers\FileHelper;
use yii\web\Controller;
use yii\web\Response;
use yii\web\UploadedFile;
use app\helpers\ActiveUser;

class FileController extends Controller
{
    use ModuleTrait;

    public function actionUpload()
    {
        $model = new UploadForm();
        $model->file = UploadedFile::getInstances($model, 'file');

        if ($model->rules()[0]['maxFiles'] == 1 && sizeof($model->file) == 1) {
            $model->file = $model->file[0];
        }

        if ($model->file && $model->validate()) {
            $result['uploadedFiles'] = [];
            if (is_array($model->file)) {
                foreach ($model->file as $file) {
                    $path = $this->getModule()->getUserDirPath() . DIRECTORY_SEPARATOR . $file->name;
                    $file->saveAs($path);
                    $result['uploadedFiles'][] = $file->name;
                }
            } else {
                $path = $this->getModule()->getUserDirPath() . DIRECTORY_SEPARATOR . $model->file->name;
                $model->file->saveAs($path);
                $result['uploadedFiles'][] = $model->file->name;
            }
            Yii::$app->response->format = Response::FORMAT_JSON;
            return $result;
        } else {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return [
                'error' => $model->getErrors('file')
            ];
        }
    }

    public function actionDownload($id)
    {
        $file = File::findOne(['id' => $id]);
        $filePath = $this->getModule()->getFilesDirPathAws($file->hash) . DIRECTORY_SEPARATOR . $file->hash . '.' . $file->type;
        $content = Yii::$app->awss3Fs->read("attachments".DIRECTORY_SEPARATOR.$filePath);
        return Yii::$app->response->sendContentAsFile($content, "$file->name.$file->type");
    }

    public function actionDelete($id)
    {
        //check user permission
        if (!$this->checkPermission($id)){
            Yii::$app->session->setFlash('error', "Anda tidak memiliki Permission untuk menghapus file ini / file tidak tersedia.");
            return $this->goBack();
        }

        if ($this->getModule()->detachFileAws($id)) {
            Yii::$app->session->setFlash('success', "File berhasil di hapus.");
            return $this->goBack();
        } else {
            return false;
        }
    }

    public function checkPermission($id){

        $model = File::findOne(['id' => $id]);
        $userID = (\Yii::$app->user->isGuest) ? "" : \Yii::$app->user->identity->id;

        if (empty($model)) return false;


        if (Yii::$app->user->can('update-all-post') ||Yii::$app->user->can('update-terminal-post',$model) ||Yii::$app->user->can('update-own-post',$model)){
            return true;
        } else {
            return false;
        }

    }

    public function actionDownloadTemp($filename)
    {
        $filePath = $this->getModule()->getUserDirPath() . DIRECTORY_SEPARATOR . $filename;

        return Yii::$app->response->sendFile($filePath, $filename);
    }

    public function actionDeleteTemp($filename)
    {
        $userTempDir = $this->getModule()->getUserDirPath();
        $filePath = $userTempDir . DIRECTORY_SEPARATOR . $filename;
        unlink($filePath);
        if (!sizeof(FileHelper::findFiles($userTempDir))) {
            rmdir($userTempDir);
        }

        Yii::$app->response->format = Response::FORMAT_JSON;
        return [];
    }
}
