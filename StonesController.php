<?php
class StonesController extends AppController {
	
	public function index(){
		$elements = $this->Stone->find('all', array('order' => array('Stone.name' => 'ASC')));
                //pr('<br /><br /><br />' . count($elements));
		$this->set('elements', $elements);
	}
	
	public function edit($id){
		if (!empty($this->request->data)) {
			
			$this->Stone->create();
			if ($this->Stone->save($this->request->data)) {
                $this->redirect(array('action' => 'index'));
            }
		} else {
			$this->Stone->locale = 'eng';
			$this->data = $this->Stone->read(null, $id);
		}
	}
	
	public function add(){
		if (!empty($this->request->data)) {
            $this->Stone->locale = Configure::read('Config.languages');
            $this->Stone->create();
            if ($this->Stone->save($this->request->data)) {
                $this->redirect(array('action' => 'index'));
            }
        }
	}
}