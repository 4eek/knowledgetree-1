/*
 * Ext JS Library 1.1 Beta 1
 * Copyright(c) 2006-2007, Ext JS, LLC.
 * licensing@extjs.com
 * 
 * http://www.extjs.com/license
 */


Ext.menu.Menu=function(_1){Ext.apply(this,_1);this.id=this.id||Ext.id();this.addEvents({beforeshow:true,beforehide:true,show:true,hide:true,click:true,mouseover:true,mouseout:true,itemclick:true});Ext.menu.MenuMgr.register(this);var _2=this.items;this.items=new Ext.util.MixedCollection();if(_2){this.add.apply(this,_2);}};Ext.extend(Ext.menu.Menu,Ext.util.Observable,{minWidth:120,shadow:"sides",subMenuAlign:"tl-tr?",defaultAlign:"tl-bl?",allowOtherMenus:false,hidden:true,render:function(){if(this.el){return;}var el=this.el=new Ext.Layer({cls:"x-menu",shadow:this.shadow,constrain:false,parentEl:this.parentEl||document.body,zindex:15000});this.keyNav=new Ext.menu.MenuNav(this);if(this.plain){el.addClass("x-menu-plain");}if(this.cls){el.addClass(this.cls);}this.focusEl=el.createChild({tag:"a",cls:"x-menu-focus",href:"#",onclick:"return false;",tabIndex:"-1"});var ul=el.createChild({tag:"ul",cls:"x-menu-list"});ul.on("click",this.onClick,this);ul.on("mouseover",this.onMouseOver,this);ul.on("mouseout",this.onMouseOut,this);this.items.each(function(_5){var li=document.createElement("li");li.className="x-menu-list-item";ul.dom.appendChild(li);_5.render(li,this);},this);this.ul=ul;this.autoWidth();},autoWidth:function(){var el=this.el,ul=this.ul;if(!el){return;}var w=this.width;if(w){el.setWidth(w);}else{if(Ext.isIE){el.setWidth(this.minWidth);var t=el.dom.offsetWidth;el.setWidth(ul.getWidth()+el.getFrameWidth("lr"));}}},delayAutoWidth:function(){if(this.rendered){if(!this.awTask){this.awTask=new Ext.util.DelayedTask(this.autoWidth,this);}this.awTask.delay(20);}},findTargetItem:function(e){var t=e.getTarget(".x-menu-list-item",this.ul,true);if(t&&t.menuItemId){return this.items.get(t.menuItemId);}},onClick:function(e){var t;if(t=this.findTargetItem(e)){t.onClick(e);this.fireEvent("click",this,t,e);}},setActiveItem:function(_f,_10){if(_f!=this.activeItem){if(this.activeItem){this.activeItem.deactivate();}this.activeItem=_f;_f.activate(_10);}else{if(_10){_f.expandMenu();}}},tryActivate:function(_11,_12){var _13=this.items;for(var i=_11,len=_13.length;i>=0&&i<len;i+=_12){var _16=_13.get(i);if(!_16.disabled&&_16.canActivate){this.setActiveItem(_16,false);return _16;}}return false;},onMouseOver:function(e){var t;if(t=this.findTargetItem(e)){if(t.canActivate&&!t.disabled){this.setActiveItem(t,true);}}this.fireEvent("mouseover",this,e,t);},onMouseOut:function(e){var t;if(t=this.findTargetItem(e)){if(t==this.activeItem&&t.shouldDeactivate(e)){this.activeItem.deactivate();delete this.activeItem;}}this.fireEvent("mouseout",this,e,t);},isVisible:function(){return this.el&&!this.hidden;},show:function(el,pos,_1d){this.parentMenu=_1d;if(!this.el){this.render();}this.fireEvent("beforeshow",this);this.showAt(this.el.getAlignToXY(el,pos||this.defaultAlign),_1d,false);},showAt:function(xy,_1f,_20){this.parentMenu=_1f;if(!this.el){this.render();}if(_20!==false){this.fireEvent("beforeshow",this);}this.el.setXY(xy);this.el.show();this.hidden=false;this.focus();this.fireEvent("show",this);},focus:function(){if(!this.hidden){this.doFocus.defer(50,this);}},doFocus:function(){if(!this.hidden){this.focusEl.focus();}},hide:function(_21){if(this.el&&this.isVisible()){this.fireEvent("beforehide",this);if(this.activeItem){this.activeItem.deactivate();this.activeItem=null;}this.el.hide();this.hidden=true;this.fireEvent("hide",this);}if(_21===true&&this.parentMenu){this.parentMenu.hide(true);}},add:function(){var a=arguments,l=a.length,_24;for(var i=0;i<l;i++){var el=a[i];if(el.render){_24=this.addItem(el);}else{if(typeof el=="string"){if(el=="separator"||el=="-"){_24=this.addSeparator();}else{_24=this.addText(el);}}else{if(el.tagName||el.el){_24=this.addElement(el);}else{if(typeof el=="object"){_24=this.addMenuItem(el);}}}}}return _24;},getEl:function(){if(!this.el){this.render();}return this.el;},addSeparator:function(){return this.addItem(new Ext.menu.Separator());},addElement:function(el){return this.addItem(new Ext.menu.BaseItem(el));},addItem:function(_28){this.items.add(_28);if(this.ul){var li=document.createElement("li");li.className="x-menu-list-item";this.ul.dom.appendChild(li);_28.render(li,this);this.delayAutoWidth();}return _28;},addMenuItem:function(_2a){if(!(_2a instanceof Ext.menu.Item)){if(typeof _2a.checked=="boolean"){_2a=new Ext.menu.CheckItem(_2a);}else{_2a=new Ext.menu.Item(_2a);}}return this.addItem(_2a);},addText:function(_2b){return this.addItem(new Ext.menu.TextItem(_2b));},insert:function(_2c,_2d){this.items.insert(_2c,_2d);if(this.ul){var li=document.createElement("li");li.className="x-menu-list-item";this.ul.dom.insertBefore(li,this.ul.dom.childNodes[_2c]);_2d.render(li,this);this.delayAutoWidth();}return _2d;},remove:function(_2f){this.items.removeKey(_2f.id);_2f.destroy();},removeAll:function(){var f;while(f=this.items.first()){this.remove(f);}}});Ext.menu.MenuNav=function(_31){Ext.menu.MenuNav.superclass.constructor.call(this,_31.el);this.scope=this.menu=_31;};Ext.extend(Ext.menu.MenuNav,Ext.KeyNav,{doRelay:function(e,h){var k=e.getKey();if(!this.menu.activeItem&&e.isNavKeyPress()&&k!=e.SPACE&&k!=e.RETURN){this.menu.tryActivate(0,1);return false;}return h.call(this.scope||this,e,this.menu);},up:function(e,m){if(!m.tryActivate(m.items.indexOf(m.activeItem)-1,-1)){m.tryActivate(m.items.length-1,-1);}},down:function(e,m){if(!m.tryActivate(m.items.indexOf(m.activeItem)+1,1)){m.tryActivate(0,1);}},right:function(e,m){if(m.activeItem){m.activeItem.expandMenu(true);}},left:function(e,m){m.hide();if(m.parentMenu&&m.parentMenu.activeItem){m.parentMenu.activeItem.activate();}},enter:function(e,m){if(m.activeItem){e.stopPropagation();m.activeItem.onClick(e);m.fireEvent("click",this,m.activeItem);return true;}}});

Ext.menu.MenuMgr=function(){var _1,_2,_3={},_4=false,_5=new Date();function init(){_1={},_2=new Ext.util.MixedCollection();Ext.get(document).addKeyListener(27,function(){if(_2.length>0){hideAll();}});}function hideAll(){if(_2.length>0){var c=_2.clone();c.each(function(m){m.hide();});}}function onHide(m){_2.remove(m);if(_2.length<1){Ext.get(document).un("mousedown",onMouseDown);_4=false;}}function onShow(m){var _a=_2.last();_5=new Date();_2.add(m);if(!_4){Ext.get(document).on("mousedown",onMouseDown);_4=true;}if(m.parentMenu){m.getEl().setZIndex(parseInt(m.parentMenu.getEl().getStyle("z-index"),10)+3);m.parentMenu.activeChild=m;}else{if(_a&&_a.isVisible()){m.getEl().setZIndex(parseInt(_a.getEl().getStyle("z-index"),10)+3);}}}function onBeforeHide(m){if(m.activeChild){m.activeChild.hide();}if(m.autoHideTimer){clearTimeout(m.autoHideTimer);delete m.autoHideTimer;}}function onBeforeShow(m){var pm=m.parentMenu;if(!pm&&!m.allowOtherMenus){hideAll();}else{if(pm&&pm.activeChild){pm.activeChild.hide();}}}function onMouseDown(e){if(_5.getElapsed()>50&&_2.length>0&&!e.getTarget(".x-menu")){hideAll();}}function onBeforeCheck(mi,_10){if(_10){var g=_3[mi.group];for(var i=0,l=g.length;i<l;i++){if(g[i]!=mi){g[i].setChecked(false);}}}}return{hideAll:function(){hideAll();},register:function(_14){if(!_1){init();}_1[_14.id]=_14;_14.on("beforehide",onBeforeHide);_14.on("hide",onHide);_14.on("beforeshow",onBeforeShow);_14.on("show",onShow);var g=_14.group;if(g&&_14.events["checkchange"]){if(!_3[g]){_3[g]=[];}_3[g].push(_14);_14.on("checkchange",onCheck);}},get:function(_16){if(typeof _16=="string"){return _1[_16];}else{if(_16.events){return _16;}else{if(typeof _16.length=="number"){return new Ext.menu.Menu({items:_16});}else{return new Ext.menu.Menu(_16);}}}},unregister:function(_17){delete _1[_17.id];_17.un("beforehide",onBeforeHide);_17.un("hide",onHide);_17.un("beforeshow",onBeforeShow);_17.un("show",onShow);var g=_17.group;if(g&&_17.events["checkchange"]){_3[g].remove(_17);_17.un("checkchange",onCheck);}},registerCheckable:function(_19){var g=_19.group;if(g){if(!_3[g]){_3[g]=[];}_3[g].push(_19);_19.on("beforecheckchange",onBeforeCheck);}},unregisterCheckable:function(_1b){var g=_1b.group;if(g){_3[g].remove(_1b);_1b.un("beforecheckchange",onBeforeCheck);}}};}();

Ext.menu.BaseItem=function(_1){Ext.menu.BaseItem.superclass.constructor.call(this,_1);this.addEvents({click:true,activate:true,deactivate:true});if(this.handler){this.on("click",this.handler,this.scope,true);}};Ext.extend(Ext.menu.BaseItem,Ext.Component,{canActivate:false,activeClass:"x-menu-item-active",hideOnClick:true,hideDelay:100,ctype:"Ext.menu.BaseItem",actionMode:"container",render:function(_2,_3){this.parentMenu=_3;Ext.menu.BaseItem.superclass.render.call(this,_2);this.container.menuItemId=this.id;},onRender:function(_4,_5){this.el=Ext.get(this.el);_4.dom.appendChild(this.el.dom);},onClick:function(e){if(!this.disabled&&this.fireEvent("click",this,e)!==false&&this.parentMenu.fireEvent("itemclick",this,e)!==false){this.handleClick(e);}else{e.stopEvent();}},activate:function(){if(this.disabled){return false;}var li=this.container;li.addClass(this.activeClass);this.region=li.getRegion().adjust(2,2,-2,-2);this.fireEvent("activate",this);return true;},deactivate:function(){this.container.removeClass(this.activeClass);this.fireEvent("deactivate",this);},shouldDeactivate:function(e){return!this.region||!this.region.contains(e.getPoint());},handleClick:function(e){if(this.hideOnClick){this.parentMenu.hide.defer(this.hideDelay,this.parentMenu,[true]);}},expandMenu:function(_a){},hideMenu:function(){}});

Ext.menu.TextItem=function(_1){this.text=_1;Ext.menu.TextItem.superclass.constructor.call(this);};Ext.extend(Ext.menu.TextItem,Ext.menu.BaseItem,{hideOnClick:false,itemCls:"x-menu-text",onRender:function(){var s=document.createElement("span");s.className=this.itemCls;s.innerHTML=this.text;this.el=s;Ext.menu.TextItem.superclass.onRender.apply(this,arguments);}});

Ext.menu.Separator=function(_1){Ext.menu.Separator.superclass.constructor.call(this,_1);};Ext.extend(Ext.menu.Separator,Ext.menu.BaseItem,{itemCls:"x-menu-sep",hideOnClick:false,onRender:function(li){var s=document.createElement("span");s.className=this.itemCls;s.innerHTML="&#160;";this.el=s;li.addClass("x-menu-sep-li");Ext.menu.Separator.superclass.onRender.apply(this,arguments);}});

Ext.menu.Item=function(_1){Ext.menu.Item.superclass.constructor.call(this,_1);if(this.menu){this.menu=Ext.menu.MenuMgr.get(this.menu);}};Ext.extend(Ext.menu.Item,Ext.menu.BaseItem,{itemCls:"x-menu-item",canActivate:true,ctype:"Ext.menu.Item",onRender:function(_2,_3){var el=document.createElement("a");el.hideFocus=true;el.unselectable="on";el.href=this.href||"#";if(this.hrefTarget){el.target=this.hrefTarget;}el.className=this.itemCls+(this.menu?" x-menu-item-arrow":"")+(this.cls?" "+this.cls:"");el.innerHTML=String.format("<img src=\"{0}\" class=\"x-menu-item-icon {2}\" />{1}",this.icon||Ext.BLANK_IMAGE_URL,this.text,this.iconCls||"");this.el=el;Ext.menu.Item.superclass.onRender.call(this,_2,_3);},setText:function(_5){this.text=_5;if(this.rendered){this.el.update(String.format("<img src=\"{0}\" class=\"x-menu-item-icon {2}\">{1}",this.icon||Ext.BLANK_IMAGE_URL,this.text,this.iconCls||""));this.parentMenu.autoWidth();}},handleClick:function(e){if(!this.href){e.stopEvent();}Ext.menu.Item.superclass.handleClick.apply(this,arguments);},activate:function(_7){if(Ext.menu.Item.superclass.activate.apply(this,arguments)){this.focus();if(_7){this.expandMenu();}}return true;},shouldDeactivate:function(e){if(Ext.menu.Item.superclass.shouldDeactivate.call(this,e)){if(this.menu&&this.menu.isVisible()){return!this.menu.getEl().getRegion().contains(e.getPoint());}return true;}return false;},deactivate:function(){Ext.menu.Item.superclass.deactivate.apply(this,arguments);this.hideMenu();},expandMenu:function(_9){if(!this.disabled&&this.menu){if(!this.menu.isVisible()){this.menu.show(this.container,this.parentMenu.subMenuAlign||"tl-tr?",this.parentMenu);}if(_9){this.menu.tryActivate(0,1);}}},hideMenu:function(){if(this.menu&&this.menu.isVisible()){this.menu.hide();}}});

Ext.menu.CheckItem=function(_1){Ext.menu.CheckItem.superclass.constructor.call(this,_1);this.addEvents({"beforecheckchange":true,"checkchange":true});if(this.checkHandler){this.on("checkchange",this.checkHandler,this.scope);}};Ext.extend(Ext.menu.CheckItem,Ext.menu.Item,{itemCls:"x-menu-item x-menu-check-item",groupClass:"x-menu-group-item",checked:false,ctype:"Ext.menu.CheckItem",onRender:function(c){Ext.menu.CheckItem.superclass.onRender.apply(this,arguments);if(this.group){this.el.addClass(this.groupClass);}Ext.menu.MenuMgr.registerCheckable(this);if(this.checked){this.checked=false;this.setChecked(true,true);}},destroy:function(){if(this.rendered){Ext.menu.MenuMgr.unregisterCheckable(this);}Ext.menu.CheckItem.superclass.destroy.apply(this,arguments);},setChecked:function(_3,_4){if(this.checked!=_3&&this.fireEvent("beforecheckchange",this,_3)!==false){if(this.container){this.container[_3?"addClass":"removeClass"]("x-menu-item-checked");}this.checked=_3;if(_4!==true){this.fireEvent("checkchange",this,_3);}}},handleClick:function(e){if(!this.disabled&&!(this.checked&&this.group)){this.setChecked(!this.checked);}Ext.menu.CheckItem.superclass.handleClick.apply(this,arguments);}});

Ext.menu.Adapter=function(_1,_2){Ext.menu.Adapter.superclass.constructor.call(this,_2);this.component=_1;};Ext.extend(Ext.menu.Adapter,Ext.menu.BaseItem,{canActivate:true,onRender:function(_3,_4){this.component.render(_3);this.el=this.component.getEl();},activate:function(){if(this.disabled){return false;}this.component.focus();this.fireEvent("activate",this);return true;},deactivate:function(){this.fireEvent("deactivate",this);},disable:function(){this.component.disable();Ext.menu.Adapter.superclass.disable.call(this);},enable:function(){this.component.enable();Ext.menu.Adapter.superclass.enable.call(this);}});

Ext.menu.DateItem=function(_1){Ext.menu.DateItem.superclass.constructor.call(this,new Ext.DatePicker(_1),_1);this.picker=this.component;this.addEvents({select:true});this.picker.on("render",function(_2){_2.getEl().swallowEvent("click");_2.container.addClass("x-menu-date-item");});this.picker.on("select",this.onSelect,this);};Ext.extend(Ext.menu.DateItem,Ext.menu.Adapter,{onSelect:function(_3,_4){this.fireEvent("select",this,_4,_3);Ext.menu.DateItem.superclass.handleClick.call(this);}});

Ext.menu.ColorItem=function(_1){Ext.menu.ColorItem.superclass.constructor.call(this,new Ext.ColorPalette(_1),_1);this.palette=this.component;this.relayEvents(this.palette,["select"]);if(this.selectHandler){this.on("select",this.selectHandler,this.scope);}};Ext.extend(Ext.menu.ColorItem,Ext.menu.Adapter);

Ext.menu.DateMenu=function(_1){Ext.menu.DateMenu.superclass.constructor.call(this,_1);this.plain=true;var di=new Ext.menu.DateItem(_1);this.add(di);this.picker=di.picker;this.relayEvents(di,["select"]);this.on("beforeshow",function(){if(this.picker){this.picker.hideMonthPicker(true);}},this);};Ext.extend(Ext.menu.DateMenu,Ext.menu.Menu,{cls:"x-date-menu"});

Ext.menu.ColorMenu=function(_1){Ext.menu.ColorMenu.superclass.constructor.call(this,_1);this.plain=true;var ci=new Ext.menu.ColorItem(_1);this.add(ci);this.palette=ci.palette;this.relayEvents(ci,["select"]);};Ext.extend(Ext.menu.ColorMenu,Ext.menu.Menu);